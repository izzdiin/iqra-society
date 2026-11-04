<?php

require_once __DIR__ . '/../includes/auth_middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function profile_links_current_user(mysqli $conn): ?array
{
    if (
        empty($_SESSION['is_logged_in']) ||
        $_SESSION['is_logged_in'] !== true
    ) {
        return null;
    }

    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $username = trim(strtolower((string) ($_SESSION['username'] ?? '')));

    if ($user_id > 0) {
        $stmt = $conn->prepare('SELECT id, username, fullname, avatar FROM users WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($user) {
                return $user;
            }
        }
    }

    if ($username !== '') {
        $stmt = $conn->prepare('SELECT id, username, fullname, avatar FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($user) {
                $_SESSION['user_id'] = (int) $user['id'];
                return $user;
            }
        }
    }

    return null;
}

$user = profile_links_current_user($conn);

if (!$user) {
    $redirect = '/profile/links';
    header('Location: /login?redirect=' . rawurlencode($redirect));
    exit;
}

if (empty($_SESSION['csrf_profile_links'])) {
    $_SESSION['csrf_profile_links'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_profile_links'];
$profile_url = '/' . rawurlencode(strtolower($user['username']));
$display_name = trim((string) ($user['fullname'] ?? '')) ?: $user['username'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tautan - <?= e($display_name) ?> | IQRA' Society</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#065f46'
                    }
                }
            }
        };
    </script>
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .safe-bottom { padding-bottom: max(1.5rem, env(safe-area-inset-bottom)); }
        dialog::backdrop { background: rgba(15, 23, 42, .55); backdrop-filter: blur(3px); }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">

<header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <a href="<?= e($profile_url) ?>" class="flex min-w-0 items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-800">
                <i class="fa-solid fa-link"></i>
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-extrabold text-slate-900">Kelola Tautan</p>
                <p class="truncate text-[11px] font-medium text-slate-500">@<?= e($user['username']) ?></p>
            </div>
        </a>
        <a href="<?= e($profile_url) ?>" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-emerald-200 bg-white px-3 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-50">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
            <span class="hidden sm:inline">Lihat profil</span>
        </a>
    </div>
</header>

<main class="mx-auto w-full max-w-3xl px-4 py-5 sm:px-6 sm:py-8">
    <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-emerald-950 via-emerald-800 to-teal-600 px-5 py-6 text-white sm:px-7">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                    <i class="fa-solid fa-list-check text-lg"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-100">Mini website</p>
                    <h1 class="mt-1 text-xl font-extrabold tracking-tight sm:text-2xl">Tautan penting Anda</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-emerald-50/85">Tambahkan semua alamat penting yang ingin pengunjung temukan dari satu halaman profil.</p>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">Daftar tautan</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Gunakan tombol panah untuk mengatur urutan. Tautan nonaktif tidak akan tampil di profil publik.</p>
                </div>
                <button type="button" id="add-link" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-800 px-4 py-3 text-xs font-extrabold text-white shadow-sm hover:bg-emerald-900 focus:outline-none focus:ring-4 focus:ring-emerald-100">
                    <i class="fa-solid fa-plus"></i> Tambah tautan
                </button>
            </div>

            <div id="notice" class="mt-4 hidden rounded-2xl px-4 py-3 text-xs font-bold" aria-live="polite"></div>
            <div id="links-list" class="mt-5 space-y-3"></div>

            <div id="empty-state" class="mt-5 hidden rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm">
                    <i class="fa-solid fa-link text-xl"></i>
                </div>
                <p class="mt-4 text-sm font-extrabold text-slate-700">Belum ada tautan</p>
                <p class="mx-auto mt-1 max-w-sm text-xs leading-5 text-slate-500">Tambahkan WhatsApp, Instagram, YouTube, website, formulir, toko, donasi, atau tautan penting lainnya.</p>
            </div>
        </div>
    </section>

    <section class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 sm:mt-5 sm:p-5">
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm"><i class="fa-solid fa-circle-info"></i></div>
            <div>
                <p class="text-xs font-extrabold text-emerald-950">URL profil</p>
                <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <code class="break-all rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-emerald-800"><?= e($profile_url) ?></code>
                    <button type="button" id="copy-profile" class="inline-flex w-fit items-center gap-1.5 text-xs font-bold text-emerald-800 hover:text-emerald-950"><i class="fa-regular fa-copy"></i> Salin</button>
                </div>
            </div>
        </div>
    </section>

    <div class="mt-5 flex items-center justify-between gap-3 px-1 text-xs">
        <a href="/" class="font-bold text-slate-500 hover:text-emerald-800"><i class="fa-solid fa-house mr-1"></i>Beranda</a>
        <a href="/logout" class="font-bold text-red-600 hover:text-red-700"><i class="fa-solid fa-right-from-bracket mr-1"></i>Keluar</a>
    </div>
</main>

<dialog id="link-dialog" class="w-[calc(100%-2rem)] max-w-lg rounded-[1.5rem] border-0 bg-white p-0 shadow-2xl">
    <form id="link-form" class="overflow-hidden">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[.16em] text-emerald-700">Kelola tautan</p>
                <h2 id="dialog-title" class="mt-1 text-lg font-extrabold text-slate-900">Tambah tautan</h2>
            </div>
            <button type="button" id="close-dialog" class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="space-y-4 px-5 py-5 sm:px-6">
            <input type="hidden" id="link-id" name="id" value="">

            <label class="block">
                <span class="text-xs font-extrabold text-slate-700">Judul tautan <span class="text-rose-500">*</span></span>
                <input id="link-title" name="title" type="text" maxlength="120" required placeholder="Contoh: WhatsApp Saya" class="mt-2 min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-50">
            </label>

            <label class="block">
                <span class="text-xs font-extrabold text-slate-700">URL <span class="text-rose-500">*</span></span>
                <input id="link-url" name="url" type="text" maxlength="2048" required placeholder="https://..." class="mt-2 min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-50">
                <span class="mt-1.5 block text-[10px] leading-4 text-slate-400">Gunakan URL lengkap dengan http:// atau https://. mailto: dan tel: juga didukung.</span>
            </label>

            <label class="block">
                <span class="text-xs font-extrabold text-slate-700">Deskripsi <span class="font-medium text-slate-400">(opsional)</span></span>
                <input id="link-description" name="description" type="text" maxlength="180" placeholder="Contoh: Hubungi saya untuk konsultasi" class="mt-2 min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-50">
            </label>

            <div>
                <span class="text-xs font-extrabold text-slate-700">Ikon</span>
                <div id="icon-grid" class="mt-2 grid grid-cols-5 gap-2"></div>
            </div>

            <label class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div>
                    <p class="text-xs font-extrabold text-slate-800">Tampilkan di profil</p>
                    <p class="mt-0.5 text-[10px] text-slate-500">Matikan jika ingin menyembunyikan sementara.</p>
                </div>
                <input id="link-active" name="is_active" type="checkbox" class="h-5 w-5 accent-emerald-700" checked>
            </label>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
            <button type="button" id="cancel-dialog" class="min-h-11 rounded-xl border border-slate-200 bg-white px-4 text-xs font-extrabold text-slate-600 hover:bg-slate-100">Batal</button>
            <button type="submit" id="save-link" class="min-h-11 rounded-xl bg-emerald-800 px-5 text-xs font-extrabold text-white hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-60"><i class="fa-solid fa-floppy-disk mr-1"></i> Simpan</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    'use strict';

    const API = '/profile/profile_links_api.php';
    const CSRF = <?= json_encode($csrf_token, JSON_UNESCAPED_SLASHES) ?>;
    const PROFILE_URL = <?= json_encode($profile_url, JSON_UNESCAPED_SLASHES) ?>;
    const list = document.getElementById('links-list');
    const empty = document.getElementById('empty-state');
    const notice = document.getElementById('notice');
    const dialog = document.getElementById('link-dialog');
    const form = document.getElementById('link-form');
    const saveButton = document.getElementById('save-link');
    const iconGrid = document.getElementById('icon-grid');
    const copyButton = document.getElementById('copy-profile');

    const icons = [
        ['fa-solid fa-link', 'Link'],
        ['fa-solid fa-globe', 'Website'],
        ['fa-solid fa-book-open', 'Buku'],
        ['fa-solid fa-graduation-cap', 'Kelas'],
        ['fa-solid fa-newspaper', 'Artikel'],
        ['fa-solid fa-calendar-days', 'Kegiatan'],
        ['fa-solid fa-cart-shopping', 'Toko'],
        ['fa-solid fa-heart', 'Donasi'],
        ['fa-solid fa-envelope', 'Email'],
        ['fa-solid fa-phone', 'Telepon'],
        ['fa-solid fa-location-dot', 'Lokasi'],
        ['fa-brands fa-whatsapp', 'WhatsApp'],
        ['fa-brands fa-youtube', 'YouTube'],
        ['fa-brands fa-instagram', 'Instagram'],
        ['fa-brands fa-facebook', 'Facebook'],
        ['fa-brands fa-telegram', 'Telegram'],
        ['fa-brands fa-tiktok', 'TikTok'],
        ['fa-brands fa-x-twitter', 'X'],
    ];

    let links = [];
    let editingId = 0;
    let selectedIcon = 'fa-solid fa-link';

    function showNotice(message, type) {
        notice.textContent = message || '';
        notice.className = 'mt-4 rounded-2xl px-4 py-3 text-xs font-bold ' + (type === 'error' ? 'bg-rose-50 text-rose-700 border border-rose-100' : 'bg-emerald-50 text-emerald-800 border border-emerald-100');
        notice.classList.remove('hidden');
        window.clearTimeout(showNotice.timer);
        showNotice.timer = window.setTimeout(function () { notice.classList.add('hidden'); }, 3500);
    }

    async function request(action, payload) {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', CSRF);

        Object.keys(payload || {}).forEach(function (key) {
            const value = payload[key];
            if (Array.isArray(value)) {
                value.forEach(function (item) { body.append(key + '[]', item); });
            } else {
                body.set(key, value == null ? '' : String(value));
            }
        });

        const response = await fetch(API, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        });

        const data = await response.json().catch(function () { return null; });
        if (!response.ok || !data || !data.success) {
            throw new Error(data && data.message ? data.message : 'Permintaan gagal.');
        }
        return data;
    }

    function iconLabel(icon) {
        const found = icons.find(function (item) { return item[0] === icon; });
        return found ? found[1] : 'Link';
    }

    function renderIconGrid() {
        iconGrid.innerHTML = '';
        icons.forEach(function (item) {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.icon = item[0];
            button.className = 'flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl border px-1 text-slate-500 transition ' + (selectedIcon === item[0] ? 'border-emerald-500 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-100' : 'border-slate-200 bg-white hover:border-emerald-200 hover:bg-emerald-50');
            button.innerHTML = '<i class="' + item[0] + '"></i><span class="text-[8px] font-bold leading-none">' + item[1] + '</span>';
            button.addEventListener('click', function () {
                selectedIcon = item[0];
                renderIconGrid();
            });
            iconGrid.appendChild(button);
        });
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[char];
        });
    }

    function renderLinks() {
        list.innerHTML = '';
        empty.classList.toggle('hidden', links.length !== 0);

        links.forEach(function (link, index) {
            const card = document.createElement('article');
            card.className = 'rounded-2xl border bg-white p-3 transition ' + (link.is_active ? 'border-slate-200' : 'border-dashed border-slate-300 opacity-70');
            card.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-800">
                        <i class="${escapeHtml(link.icon)}"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate text-sm font-extrabold text-slate-800">${escapeHtml(link.title)}</h3>
                            <span class="rounded-full px-2 py-0.5 text-[9px] font-extrabold ${link.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">${link.is_active ? 'AKTIF' : 'NONAKTIF'}</span>
                        </div>
                        <p class="mt-0.5 truncate text-[11px] text-slate-400">${escapeHtml(link.url)}</p>
                        ${link.description ? '<p class="mt-1 line-clamp-1 text-[10px] text-slate-500">' + escapeHtml(link.description) + '</p>' : ''}
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" data-action="up" data-id="${link.id}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-30" title="Naik" ${index === 0 ? 'disabled' : ''}><i class="fa-solid fa-chevron-up text-[11px]"></i></button>
                        <button type="button" data-action="down" data-id="${link.id}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-30" title="Turun" ${index === links.length - 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-down text-[11px]"></i></button>
                        <button type="button" data-action="toggle" data-id="${link.id}" class="hidden h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 sm:flex" title="${link.is_active ? 'Nonaktifkan' : 'Aktifkan'}"><i class="fa-solid ${link.is_active ? 'fa-eye-slash' : 'fa-eye'} text-[11px]"></i></button>
                        <button type="button" data-action="edit" data-id="${link.id}" class="hidden h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 sm:flex" title="Edit"><i class="fa-solid fa-pen text-[11px]"></i></button>
                        <button type="button" data-action="delete" data-id="${link.id}" class="hidden h-9 w-9 items-center justify-center rounded-lg border border-rose-100 bg-white text-rose-500 hover:bg-rose-50 sm:flex" title="Hapus"><i class="fa-solid fa-trash text-[11px]"></i></button>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 sm:hidden">
                    <button type="button" data-action="toggle" data-id="${link.id}" class="min-h-9 rounded-lg border border-slate-200 bg-white text-[10px] font-bold text-slate-600"><i class="fa-solid ${link.is_active ? 'fa-eye-slash' : 'fa-eye'} mr-1"></i>${link.is_active ? 'Sembunyikan' : 'Aktifkan'}</button>
                    <button type="button" data-action="edit" data-id="${link.id}" class="min-h-9 rounded-lg border border-slate-200 bg-white text-[10px] font-bold text-slate-600"><i class="fa-solid fa-pen mr-1"></i>Edit</button>
                    <button type="button" data-action="delete" data-id="${link.id}" class="min-h-9 rounded-lg border border-rose-100 bg-white text-[10px] font-bold text-rose-600"><i class="fa-solid fa-trash mr-1"></i>Hapus</button>
                </div>
            `;
            list.appendChild(card);
        });
    }

    async function loadLinks() {
        list.innerHTML = '<div class="rounded-2xl border border-slate-100 bg-slate-50 py-12 text-center"><i class="fa-solid fa-circle-notch fa-spin text-xl text-emerald-700"></i><p class="mt-3 text-xs font-medium text-slate-500">Memuat tautan…</p></div>';
        try {
            const response = await fetch(API + '?action=list', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Gagal memuat tautan.');
            links = data.data.links || [];
            renderLinks();
        } catch (error) {
            list.innerHTML = '<div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-10 text-center"><i class="fa-solid fa-triangle-exclamation text-xl text-rose-500"></i><p class="mt-3 text-sm font-bold text-slate-700">Tautan belum dapat dimuat.</p><p class="mt-1 text-xs text-rose-700">' + escapeHtml(error.message) + '</p><button type="button" id="retry-load" class="mt-3 rounded-xl bg-white px-4 py-2 text-xs font-bold text-emerald-800">Coba lagi</button></div>';
            document.getElementById('retry-load').addEventListener('click', loadLinks);
        }
    }

    function openEditor(link) {
        editingId = link ? Number(link.id) : 0;
        document.getElementById('dialog-title').textContent = link ? 'Edit tautan' : 'Tambah tautan';
        document.getElementById('link-id').value = link ? link.id : '';
        document.getElementById('link-title').value = link ? link.title : '';
        document.getElementById('link-url').value = link ? link.url : '';
        document.getElementById('link-description').value = link ? (link.description || '') : '';
        document.getElementById('link-active').checked = link ? Number(link.is_active) === 1 : true;
        selectedIcon = link ? link.icon : 'fa-solid fa-link';
        renderIconGrid();
        dialog.showModal();
        window.setTimeout(function () { document.getElementById('link-title').focus(); }, 50);
    }

    function closeEditor() {
        if (dialog.open) dialog.close();
    }

    async function saveLink(event) {
        event.preventDefault();
        saveButton.disabled = true;
        const payload = {
            id: editingId,
            title: document.getElementById('link-title').value,
            url: document.getElementById('link-url').value,
            description: document.getElementById('link-description').value,
            icon: selectedIcon,
            is_active: document.getElementById('link-active').checked ? 1 : 0
        };
        try {
            const data = await request(editingId ? 'update' : 'create', payload);
            closeEditor();
            showNotice(data.message, 'success');
            await loadLinks();
        } catch (error) {
            showNotice(error.message, 'error');
        } finally {
            saveButton.disabled = false;
        }
    }

    async function reorder(index, direction) {
        const target = index + direction;
        if (target < 0 || target >= links.length) return;
        const copy = links.slice();
        const item = copy.splice(index, 1)[0];
        copy.splice(target, 0, item);
        const ids = copy.map(function (link) { return link.id; });
        try {
            await request('reorder', { ids: ids });
            links = copy;
            renderLinks();
            showNotice('Urutan tautan berhasil disimpan.', 'success');
        } catch (error) {
            showNotice(error.message, 'error');
        }
    }

    list.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const id = Number(button.dataset.id);
        const index = links.findIndex(function (link) { return Number(link.id) === id; });
        const action = button.dataset.action;
        const link = index >= 0 ? links[index] : null;
        if (!link) return;

        if (action === 'up') return reorder(index, -1);
        if (action === 'down') return reorder(index, 1);
        if (action === 'edit') return openEditor(link);

        if (action === 'toggle') {
            try {
                const data = await request('toggle', { id: id });
                link.is_active = Number(data.data.is_active);
                renderLinks();
                showNotice(data.message, 'success');
            } catch (error) { showNotice(error.message, 'error'); }
            return;
        }

        if (action === 'delete') {
            const confirmed = window.confirm('Hapus tautan “' + link.title + '”? Tindakan ini tidak dapat dibatalkan.');
            if (!confirmed) return;
            try {
                const data = await request('delete', { id: id });
                links.splice(index, 1);
                renderLinks();
                showNotice(data.message, 'success');
            } catch (error) { showNotice(error.message, 'error'); }
        }
    });

    document.getElementById('add-link').addEventListener('click', function () { openEditor(null); });
    document.getElementById('close-dialog').addEventListener('click', closeEditor);
    document.getElementById('cancel-dialog').addEventListener('click', closeEditor);
    form.addEventListener('submit', saveLink);
    dialog.addEventListener('click', function (event) { if (event.target === dialog) closeEditor(); });

    copyButton.addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(window.location.origin + PROFILE_URL);
            showNotice('Tautan profil berhasil disalin.', 'success');
        } catch (error) {
            showNotice('Tautan profil: ' + window.location.origin + PROFILE_URL, 'success');
        }
    });

    renderIconGrid();
    loadLinks();
})();
</script>
</body>
</html>
