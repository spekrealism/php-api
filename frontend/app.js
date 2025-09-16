'use strict';

const API_BASE = 'http://127.0.0.1:8000';

const els = {
  form: document.getElementById('create-form'),
  input: document.getElementById('new-title'),
  addBtn: document.getElementById('add-btn'),
  list: document.getElementById('tasks'),
  messages: document.getElementById('messages'),
};

function setMessage(text, isError = false) {
  els.messages.textContent = text || '';
  els.messages.style.color = isError ? '#ff6b6b' : '#a8b0b8';
}

async function api(path, options = {}) {
  const res = await fetch(`${API_BASE}${path}`, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });
  if (res.status === 204) return null;
  let data = null;
  try { data = await res.json(); } catch (_) { /* ignore */ }
  if (!res.ok) {
    const errMsg = data?.error?.message || `HTTP ${res.status}`;
    throw new Error(errMsg);
  }
  return data;
}

function renderTasks(tasks) {
  els.list.innerHTML = '';
  for (const t of tasks) {
    els.list.appendChild(renderTaskItem(t));
  }
  els.list.setAttribute('aria-busy', 'false');
}

function renderTaskItem(task) {
  const li = document.createElement('li');
  li.className = 'task';
  li.dataset.id = String(task.id);

  const checkbox = document.createElement('input');
  checkbox.type = 'checkbox';
  checkbox.checked = !!task.completed;
  checkbox.ariaLabel = `Выполнено: ${task.title}`;

  const title = document.createElement('span');
  title.className = 'title' + (task.completed ? ' completed' : '');
  title.textContent = task.title;

  const del = document.createElement('button');
  del.className = 'delete-btn';
  del.type = 'button';
  del.textContent = 'Удалить';

  checkbox.addEventListener('change', async () => {
    setRowBusy(li, true);
    try {
      const updated = await api(`/tasks/${task.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ completed: checkbox.checked }),
      });
      title.classList.toggle('completed', !!updated.completed);
    } catch (e) {
      checkbox.checked = !checkbox.checked;
      setMessage(String(e.message || e), true);
    } finally {
      setRowBusy(li, false);
    }
  });

  del.addEventListener('click', async () => {
    const ok = confirm('Удалить задачу?');
    if (!ok) return;
    setRowBusy(li, true);
    try {
      await api(`/tasks/${task.id}`, { method: 'DELETE' });
      li.remove();
    } catch (e) {
      setMessage(String(e.message || e), true);
    }
  });

  li.appendChild(checkbox);
  li.appendChild(title);
  li.appendChild(del);
  return li;
}

function setRowBusy(el, busy) {
  el.style.opacity = busy ? 0.6 : 1;
  for (const btn of el.querySelectorAll('input,button')) {
    btn.disabled = busy;
  }
}

async function loadTasks() {
  els.list.setAttribute('aria-busy', 'true');
  setMessage('Загрузка…');
  try {
    const tasks = await api('/tasks');
    renderTasks(tasks);
    setMessage('');
  } catch (e) {
    setMessage(String(e.message || e), true);
  }
}

els.form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const title = els.input.value.trim();
  if (!title) return;
  els.addBtn.disabled = true;
  try {
    const task = await api('/tasks', { method: 'POST', body: JSON.stringify({ title }) });
    els.list.appendChild(renderTaskItem(task));
    els.input.value = '';
    setMessage('Задача создана');
  } catch (err) {
    setMessage(String(err.message || err), true);
  } finally {
    els.addBtn.disabled = false;
  }
});

// Initial load
loadTasks();


