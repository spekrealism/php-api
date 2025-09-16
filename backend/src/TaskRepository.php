<?php
declare(strict_types=1);

// File-backed repository with locking and atomic writes

final class TaskRepository
{
    /** @var string */
    private string $storageFile;

    /**
     * @param string $storageFile Absolute path to tasks.json
     */
    public function __construct(string $storageFile)
    {
        $this->storageFile = $storageFile;
    }

    /**
     * Read all tasks from storage.
     * @return array<int,array{id:int,title:string,completed:bool}>
     */
    public function getAll(): array
    {
        [$fp, $data] = $this->readWithSharedLock();
        if ($fp !== null) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return $data;
    }

    /**
     * Create new task with auto-increment id.
     * @return array{id:int,title:string,completed:bool}
     */
    public function create(string $title): array
    {
        [$fp, $data] = $this->readWithExclusiveLock();
        $nextId = 1;
        foreach ($data as $t) {
            if (isset($t['id']) && is_int($t['id'])) {
                $nextId = max($nextId, $t['id'] + 1);
            }
        }
        $task = ['id' => $nextId, 'title' => $title, 'completed' => false];
        $data[] = $task;
        $this->atomicWriteAndUnlock($fp, $data);
        return $task;
    }

    /**
     * Update task by id (partial update for title/completed)
     * @param array{title?:string,completed?:bool} $changes
     * @return array{id:int,title:string,completed:bool}
     */
    public function update(int $id, array $changes): array
    {
        [$fp, $data] = $this->readWithExclusiveLock();
        $found = false;
        foreach ($data as &$t) {
            if (isset($t['id']) && (int)$t['id'] === $id) {
                $found = true;
                if (array_key_exists('title', $changes)) {
                    $t['title'] = (string)$changes['title'];
                }
                if (array_key_exists('completed', $changes)) {
                    $t['completed'] = (bool)$changes['completed'];
                }
                $updated = $t;
                break;
            }
        }
        unset($t);
        if (!$found) {
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('NOT_FOUND', 'Task not found', 404);
        }
        $this->atomicWriteAndUnlock($fp, $data);
        /** @var array{id:int,title:string,completed:bool} $updated */
        return $updated;
    }

    /**
     * Delete task by id.
     */
    public function delete(int $id): void
    {
        [$fp, $data] = $this->readWithExclusiveLock();
        $initialCount = count($data);
        $data = array_values(array_filter($data, static function ($t) use ($id): bool {
            return !isset($t['id']) || (int)$t['id'] !== $id;
        }));
        if (count($data) === $initialCount) {
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('NOT_FOUND', 'Task not found', 404);
        }
        $this->atomicWriteAndUnlock($fp, $data);
    }

    /**
     * Read JSON array with shared lock.
     * @return array{0:resource|null,1:array<int,array{id:int,title:string,completed:bool}>}
     */
    private function readWithSharedLock(): array
    {
        $fp = fopen($this->storageFile, 'c+');
        if ($fp === false) {
            Http::sendError('STORAGE_ERROR', 'Unable to open storage', 500);
        }
        if (!flock($fp, LOCK_SH)) {
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Unable to lock storage (shared)', 500);
        }
        $content = stream_get_contents($fp);
        if ($content === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Unable to read storage', 500);
        }
        $content = trim($content);
        if ($content === '') {
            return [$fp, []];
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Corrupted storage JSON', 500);
        }
        /** @var array<int,array{id:int,title:string,completed:bool}> $data */
        return [$fp, $data];
    }

    /**
     * Read JSON with exclusive lock.
     * @return array{0:resource,1:array<int,array{id:int,title:string,completed:bool}>}
     */
    private function readWithExclusiveLock(): array
    {
        $fp = fopen($this->storageFile, 'c+');
        if ($fp === false) {
            Http::sendError('STORAGE_ERROR', 'Unable to open storage', 500);
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Unable to lock storage (exclusive)', 500);
        }
        // Move pointer to start before reading
        if (fseek($fp, 0) !== 0) {
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Unable to seek storage', 500);
        }
        $content = stream_get_contents($fp);
        if ($content === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Unable to read storage', 500);
        }
        $content = trim($content);
        $data = $content === '' ? [] : json_decode($content, true);
        if (!is_array($data)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Corrupted storage JSON', 500);
        }
        /** @var resource $fp */
        /** @var array<int,array{id:int,title:string,completed:bool}> $data */
        return [$fp, $data];
    }

    /**
     * Atomically write data and unlock file handle.
     * @param resource $fp
     * @param array<int,array{id:int,title:string,completed:bool}> $data
     */
    private function atomicWriteAndUnlock($fp, array $data): void
    {
        $dir = dirname($this->storageFile);
        $tmp = tempnam($dir, 'tasks_');
        if ($tmp === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Unable to create temp file', 500);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            unlink($tmp);
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Failed to encode JSON', 500);
        }
        if (file_put_contents($tmp, $json) === false) {
            unlink($tmp);
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Unable to write temp file', 500);
        }
        // Ensure pointer at start before truncating
        if (ftruncate($fp, 0) === false || fseek($fp, 0) !== 0) {
            // Even though we write via rename, keep file consistent
        }
        if (!rename($tmp, $this->storageFile)) {
            unlink($tmp);
            flock($fp, LOCK_UN);
            fclose($fp);
            Http::sendError('STORAGE_ERROR', 'Unable to replace storage file', 500);
        }
        // Release lock and close
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}


