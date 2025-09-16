# Мини‑REST API (PHP) + фронтенд (HTML/JS)

Небольшой ToDo‑сервис: REST API на чистом PHP (без фреймворков) и одностраничный фронтенд на нативном HTML/CSS/JS.

## Требования

- PHP 8.0+
- macOS/Linux/Windows
- Браузер для фронтенда

## Структура проекта

```
project/
├─ backend/
│  ├─ index.php            # единая точка входа и роутер
│  ├─ storage/
│  │  └─ tasks.json        # файл‑хранилище
│  └─ src/
│     ├─ Router.php
│     ├─ TaskRepository.php
│     ├─ Http.php
│     └─ Validation.php
├─ frontend/
│  ├─ index.html
│  ├─ style.css
│  └─ app.js
└─ README.md
```

## Как запустить

1) Инициализируйте хранилище:
- создайте файл `backend/storage/tasks.json` со строкой `[]`
- убедитесь, что у PHP есть права на запись в `backend/storage/`

2) Запустите backend (встроенный сервер PHP):

```bash
php -S 127.0.0.1:8000 -t backend backend/index.php
```

3) Откройте фронтенд:
- просто откройте файл `frontend/index.html` в браузере
- либо отдавайте статикой: `php -S 127.0.0.1:8080`

Фронтенд по умолчанию обращается к API `http://127.0.0.1:8000` (см. `frontend/app.js`, константа `API_BASE`).

## Контракты API

Модель задачи: `id:number`, `title:string`, `completed:boolean`.

- `GET /tasks` → 200
```json
[
  {"id":1, "title":"Buy milk", "completed":false}
]
```

- `POST /tasks` → 201
Запрос:
```json
{"title": "New task"}
```
Ответ:
```json
{"id": 2, "title": "New task", "completed": false}
```

- `PATCH /tasks/{id}` → 200
Запрос (минимум одно поле):
```json
{"completed": true}
```
Ответ:
```json
{"id": 2, "title": "New task", "completed": true}
```

- `DELETE /tasks/{id}` → 204 (без тела)

### Ошибки (единый формат)

```json
{"error": {"code": "VALIDATION_ERROR", "message": "title is required"}}
```

Коды: `200`, `201`, `204`, `400`, `404`, `409` (зарезервирован), `500`.
Все ответы имеют заголовок `Content-Type: application/json; charset=utf-8`.

## Проверка через cURL

```bash
# список
curl -s http://127.0.0.1:8000/tasks

# создание
curl -s -X POST http://127.0.0.1:8000/tasks \
  -H 'Content-Type: application/json' \
  -d '{"title":"Write tests"}'

# обновление статуса
curl -s -X PATCH http://127.0.0.1:8000/tasks/1 \
  -H 'Content-Type: application/json' \
  -d '{"completed":true}'

# удаление
curl -i -s -X DELETE http://127.0.0.1:8000/tasks/1
```

## Детали реализации

- Роутинг: парсинг `REQUEST_METHOD` и `REQUEST_URI`, нормализация путей (`/tasks`, `/tasks/{id}`)
- Хранилище: `storage/tasks.json`, чтение/запись с `flock` (LOCK_SH/LOCK_EX) и атомарная запись через временный файл + `rename`
- Генерация `id`: авто‑инкремент на основе максимального существующего
- Валидация: `title` — строка `1..200` (после `trim()`), `completed` — строго `boolean`
- CORS: `Access-Control-Allow-Origin: *`, методы/заголовки явно разрешены, поддержка preflight `OPTIONS`
- Защита фронтенда: использование `textContent` вместо `innerHTML` при выводе
- Ограничение размера тела запроса: по умолчанию 16KB; проверяется `Content-Type: application/json`

## Замечания

- Для простоты `409 CONFLICT` не воспроизводится в этой версии, но код зарезервирован для сценариев конкурирующих записей
- Можно запускать фронтенд и backend из разных портов: CORS включён