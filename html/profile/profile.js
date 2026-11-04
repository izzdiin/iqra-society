/**
 * Profile Page Scripts
 * IQRA' Society
 */

// 1. Share & Copy Profile URL
(function () {
    'use strict';

    const shareButton = document.getElementById('share-profile');
    const copyButton = document.getElementById('copy-profile');
    const feedback = document.getElementById('share-feedback');
    const profileUrl = window.location.href;

    function copied() {
        if (feedback) feedback.textContent = 'Tautan profil berhasil disalin.';
    }

    if (shareButton) {
        shareButton.addEventListener('click', async function () {
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: document.title,
                        text: 'Lihat profil ini di IQRA’ Society',
                        url: profileUrl
                    });
                } catch (error) {
                    if (error && error.name !== 'AbortError' && feedback) {
                        feedback.textContent = 'Berbagi dibatalkan atau tidak tersedia.';
                    }
                }
            } else {
                try {
                    await navigator.clipboard.writeText(profileUrl);
                    copied();
                } catch (error) {
                    if (feedback) feedback.textContent = 'Salin alamat dari bilah alamat browser Anda.';
                }
            }
        });
    }

    if (copyButton) {
        copyButton.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(profileUrl);
                copied();
            } catch (error) {
                const field = document.createElement('textarea');
                field.value = profileUrl;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.appendChild(field);
                field.select();
                const ok = document.execCommand('copy');
                field.remove();
                if (feedback) {
                    feedback.textContent = ok ? 'Tautan profil berhasil disalin.' : 'Salin alamat dari bilah alamat browser Anda.';
                }
            }
        });
    }
})();

// 2. Tab Navigation & Content AJAX Loader
(function () {
    'use strict';

    const container = document.getElementById('profile-tab-content');
    const result = document.getElementById('profile-tab-result');
    const loading = document.getElementById('profile-tab-loading');
    const error = document.getElementById('profile-tab-error');
    const retry = document.getElementById('profile-tab-retry');
    const buttons = document.querySelectorAll('[data-tab]');

    if (!container || !result || !loading || !error) {
        return;
    }

    const username = container.dataset.username;
    const cache = {};
    let activeTab = 'kegiatan';

    function setActiveButton(tabId) {
        buttons.forEach(function (button) {
            const isActive = button.dataset.tab === tabId;

            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.classList.toggle('bg-emerald-800', isActive);
            button.classList.toggle('text-white', isActive);
            button.classList.toggle('shadow-sm', isActive);
            if (isActive) {
                button.classList.remove('bg-amber-50', 'text-amber-900', 'bg-sky-50', 'text-sky-900', 'bg-violet-50', 'text-violet-900');
            } else {
                const palette = {
                    kegiatan: ['bg-emerald-50', 'text-emerald-900'],
                    buku: ['bg-amber-50', 'text-amber-900'],
                    kelas: ['bg-sky-50', 'text-sky-900'],
                    artikel: ['bg-violet-50', 'text-violet-900']
                };
                button.classList.remove('bg-emerald-800', 'text-white', 'shadow-sm');
                (palette[button.dataset.tab] || []).forEach(function (name) {
                    button.classList.add(name);
                });
            }
        });
    }

    function showLoading() {
        error.classList.add('hidden');
        result.classList.add('hidden');
        loading.classList.remove('hidden');
    }

    function showResult(html) {
        loading.classList.add('hidden');
        error.classList.add('hidden');
        result.innerHTML = html;
        result.classList.remove('hidden');
    }

    function showError() {
        loading.classList.add('hidden');
        result.classList.add('hidden');
        error.classList.remove('hidden');
    }

    function loadTab(tabId, forceReload) {
        activeTab = tabId;
        setActiveButton(tabId);

        if (!forceReload && cache[tabId]) {
            showResult(cache[tabId]);
            return;
        }

        showLoading();

        const url = 'profile/profile_tabs.php?username=' +
            encodeURIComponent(username) +
            '&tab=' +
            encodeURIComponent(tabId);

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(function (html) {
            cache[tabId] = html;
            showResult(html);
        })
        .catch(function () {
            showError();
        });
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            const tabId = button.dataset.tab;
            if (!tabId) return;
            loadTab(tabId, false);
        });
    });

    if (retry) {
        retry.addEventListener('click', function () {
            loadTab(activeTab, true);
        });
    }

    loadTab('kegiatan', false);
})();

// 3. Profile Builder & Link Manager (Owner Only)
(function () {
    'use strict';

    const sheet = document.getElementById('builder-sheet');
    if (!sheet) return; // Keluar jika bukan pemilik profil (is_owner === false)

    const coverView = document.getElementById('builder-cover-view');
    const linksView = document.getElementById('builder-links-view');
    const sheetTitle = document.getElementById('builder-sheet-title');
    const coverForm = document.getElementById('cover-form');
    const coverColor = document.getElementById('cover_color');
    const colorValue = document.getElementById('cover-color-value');
    const coverPreview = document.getElementById('builder-cover-preview');
    const bannerInput = document.getElementById('cover_banner');
    const removeBanner = document.getElementById('remove_banner');
    const linksManager = document.getElementById('links-manager');
    const linkModal = document.getElementById('link-modal');
    const linkForm = document.getElementById('link-form');
    const linkId = document.getElementById('link-id');
    const linkType = document.getElementById('link-type');
    const linkUrl = document.getElementById('link-url');
    const linkModalTitle = document.getElementById('link-modal-title');
    const csrf = window.__PROFILE_CONFIG__?.csrfToken || '';

    const metaByIcon = window.__PROFILE_CONFIG__?.linkMeta || {};

    function post(data, formData) {
        if (formData) {
            formData.append('csrf_token', csrf);
            formData.append('ajax', '1');
            return fetch(location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(r => r.json());
        }
        const params = new URLSearchParams();
        Object.keys(data).forEach(k => {
            if (Array.isArray(data[k])) {
                data[k].forEach(v => params.append(k + '[]', v));
            } else {
                params.append(k, data[k]);
            }
        });
        params.append('csrf_token', csrf);
        params.append('ajax', '1');
        return fetch(location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: params
        }).then(r => r.json());
    }

    function toast(message, bad) {
        const box = document.getElementById('builder-toast');
        const text = document.getElementById('builder-toast-text');
        if (!box || !text) return;
        text.textContent = message;
        text.parentElement.className = 'rounded-2xl border px-4 py-3 text-sm font-bold shadow-2xl ' + 
            (bad ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-white text-slate-800');
        box.classList.remove('hidden');
        clearTimeout(window.__builderToast);
        window.__builderToast = setTimeout(() => box.classList.add('hidden'), 2600);
    }

    function openSheet(view) {
        sheet.classList.remove('hidden');
        sheet.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        setBuilderView(view || 'cover');
    }

    function closeSheet() {
        sheet.classList.add('hidden');
        sheet.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    function setBuilderView(view) {
        const isLinks = view === 'links';
        if (coverView) coverView.classList.toggle('hidden', isLinks);
        if (linksView) linksView.classList.toggle('hidden', !isLinks);
        if (sheetTitle) sheetTitle.textContent = isLinks ? 'Kelola tautan' : 'Atur tampilan';
        document.querySelectorAll('.builder-view-btn').forEach(b => {
            const on = b.dataset.builderView === view;
            b.classList.toggle('bg-emerald-800', on);
            b.classList.toggle('text-white', on);
            b.classList.toggle('bg-white', !on);
            b.classList.toggle('text-slate-600', !on);
        });
    }

    document.getElementById('open-mini-settings')?.addEventListener('click', () => openSheet('cover'));
    document.getElementById('close-builder-sheet')?.addEventListener('click', closeSheet);
    document.getElementById('cancel-builder')?.addEventListener('click', closeSheet);
    sheet.addEventListener('click', e => { if (e.target === sheet) closeSheet(); });
    document.querySelectorAll('.builder-view-btn').forEach(b => b.addEventListener('click', () => setBuilderView(b.dataset.builderView)));
    
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (linkModal && !linkModal.classList.contains('hidden')) closeLinkModal();
            else if (!sheet.classList.contains('hidden')) closeSheet();
        }
    });

    coverColor?.addEventListener('input', () => {
        if (coverPreview) coverPreview.style.backgroundColor = coverColor.value;
        if (colorValue) colorValue.textContent = coverColor.value.toLowerCase();
    });

    bannerInput?.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            toast('Ukuran banner maksimal 5 MB.', true);
            this.value = '';
            return;
        }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            toast('Banner harus JPG, PNG, atau WebP.', true);
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function (event) {
            let img = document.getElementById('builder-banner-preview');
            if (!img || img.tagName !== 'IMG') {
                img = document.createElement('img');
                img.id = 'builder-banner-preview';
                img.alt = 'Pratinjau banner';
                img.className = 'absolute inset-0 h-full w-full object-cover';
                coverPreview.prepend(img);
            }
            img.src = event.target.result;
            img.className = 'absolute inset-0 h-full w-full object-cover';
            const placeholder = coverPreview.querySelector('[data-cover-placeholder]');
            if (placeholder) placeholder.remove();
            if (!document.getElementById('builder-banner-overlay')) {
                const ov = document.createElement('div');
                ov.id = 'builder-banner-overlay';
                ov.className = 'absolute inset-0 bg-gradient-to-t from-black/45 via-black/10 to-black/15';
                coverPreview.appendChild(ov);
            }
            if (removeBanner) removeBanner.value = '0';
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('remove-banner-btn')?.addEventListener('click', () => {
        if (removeBanner) removeBanner.value = '1';
        if (bannerInput) bannerInput.value = '';
        const img = document.getElementById('builder-banner-preview');
        if (img && img.tagName === 'IMG') img.remove();
        document.getElementById('builder-banner-overlay')?.remove();
        toast('Banner ditandai untuk dihapus. Simpan untuk menerapkan.');
    });

    coverForm?.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(coverForm);
        post({}, fd).then(j => {
            if (!j.success) throw new Error(j.message);
            const color = j.cover_color || coverColor.value;
            if (coverPreview) coverPreview.style.backgroundColor = color;
            const cover = document.querySelector('section[style*="background-color"]');
            if (cover) cover.style.backgroundColor = color;
            let img = document.querySelector('section[style*="background-color"] img');
            if (j.cover_banner) {
                if (!img) {
                    img = document.createElement('img');
                    img.className = 'absolute inset-0 h-full w-full object-cover';
                    cover?.prepend(img);
                }
                img.src = j.cover_banner;
                img.style.display = '';
            } else if (img) {
                img.remove();
            }
            closeSheet();
            toast('Tampilan mini-site berhasil diperbarui.');
        }).catch(err => toast(err.message || 'Gagal menyimpan tampilan.', true));
    });

    function openLinkModal(data) {
        data = data || {};
        if (linkId) linkId.value = data.id || 0;
        if (linkType) linkType.value = data.type || 'whatsapp';
        if (linkUrl) linkUrl.value = data.url || '';
        if (linkModalTitle) linkModalTitle.textContent = data.id ? 'Edit tautan' : 'Tambah tautan';
        if (linkModal) {
            linkModal.classList.remove('hidden');
            linkModal.classList.add('flex');
            setTimeout(() => linkUrl?.focus(), 80);
        }
    }

    function closeLinkModal() {
        if (linkModal) {
            linkModal.classList.add('hidden');
            linkModal.classList.remove('flex');
        }
    }

    document.getElementById('add-link')?.addEventListener('click', () => openLinkModal());
    document.getElementById('close-link-modal')?.addEventListener('click', closeLinkModal);
    document.getElementById('cancel-link')?.addEventListener('click', closeLinkModal);
    linkModal?.addEventListener('click', e => { if (e.target === linkModal) closeLinkModal(); });
    document.querySelectorAll('.edit-link').forEach(b => b.addEventListener('click', () => openLinkModal({
        id: b.dataset.id,
        type: b.dataset.type,
        label: b.dataset.label,
        url: b.dataset.url
    })));

    function ensureEmpty() {
        if (!linksManager) return;
        const rows = linksManager.querySelectorAll('.link-row');
        let empty = document.getElementById('links-empty');
        if (!rows.length) {
            if (!empty) {
                empty = document.createElement('div');
                empty.id = 'links-empty';
                empty.className = 'rounded-2xl border border-dashed border-slate-200 py-10 text-center text-xs text-slate-400';
                empty.textContent = 'Belum ada tautan.';
                linksManager.appendChild(empty);
            }
        } else empty?.remove();
    }

    function publicContainer() {
        return document.getElementById('public-profile-links');
    }

    function renderPublicLink(link) {
        const meta = metaByIcon[link.icon] || {
            type: 'custom',
            icon: 'fa-solid fa-link',
            bg: 'bg-slate-100',
            text: 'text-slate-700'
        };

        const a = document.createElement('a');
        a.href = link.url;
        a.className = 'public-link group flex w-[72px] shrink-0 snap-start flex-col items-center justify-center gap-1.5 rounded-2xl px-1 py-2 text-center transition hover:-translate-y-0.5 sm:w-[88px]';

        if (!String(link.url).startsWith('mailto:')) {
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
        }

        a.dataset.id = link.id;
        a.innerHTML = `
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ${meta.bg} ${meta.text}">
                <i class="${meta.icon} text-xl"></i>
            </span>
            <span class="link-public-title hidden w-full truncate text-xs font-bold text-slate-800 sm:block"></span>
            <i class="link-public-arrow hidden fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-300 sm:block"></i>
        `;
        a.querySelector('.link-public-title').textContent = link.title;
        return a;
    }

    function syncPublicLinks() {
        const box = publicContainer();
        if (!box || !linksManager) return;
        box.innerHTML = '';
        const rows = [...linksManager.querySelectorAll('.link-row[data-active="1"]')];
        rows.forEach(row => {
            const link = {
                id: row.dataset.id,
                title: row.dataset.title,
                url: row.dataset.url,
                icon: row.dataset.icon
            };
            box.appendChild(renderPublicLink(link));
        });
        box.closest('section')?.classList.toggle('hidden', rows.length === 0);
    }

    function addRow(link) {
        if (!linksManager) return;
        const meta = metaByIcon[link.icon] || {
            type: 'custom',
            icon: 'fa-solid fa-link',
            bg: 'bg-slate-100',
            text: 'text-slate-700'
        };
        const row = document.createElement('div');
        row.className = 'link-row group relative flex h-[118px] w-[92px] shrink-0 snap-start flex-col items-center rounded-2xl border border-slate-200 bg-slate-50 p-2 text-center shadow-sm';
        row.draggable = true;
        row.dataset.id = link.id;
        row.dataset.active = link.is_active;
        row.dataset.title = link.title;
        row.dataset.url = link.url;
        row.dataset.icon = link.icon;
        row.innerHTML = '<button type="button" class="drag-handle absolute left-1/2 top-1 z-10 flex h-5 w-8 -translate-x-1/2 cursor-grab touch-none items-center justify-center rounded-full text-slate-300 hover:bg-white hover:text-slate-500"><i class="fa-solid fa-ellipsis text-[10px]"></i></button><span class="link-icon mt-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ' + meta.bg + ' ' + meta.text + '"><i class="' + meta.icon + ' text-lg"></i></span><p class="mt-1.5 w-full truncate text-[11px] font-extrabold text-slate-800"></p><div class="mt-auto flex w-full items-center justify-center gap-0.5 border-t border-slate-200/70 pt-1.5"><button type="button" class="move-link-up flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 hover:bg-white"><i class="fa-solid fa-chevron-left text-[9px]"></i></button><button type="button" class="toggle-link flex h-6 w-6 items-center justify-center rounded-lg text-slate-500 hover:bg-white"><i class="fa-solid ' + (Number(link.is_active) ? 'fa-eye' : 'fa-eye-slash') + ' text-[10px]"></i></button><button type="button" class="edit-link flex h-6 w-6 items-center justify-center rounded-lg text-slate-500 hover:bg-white"><i class="fa-solid fa-pen text-[9px]"></i></button><button type="button" class="move-link-down flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 hover:bg-white"><i class="fa-solid fa-chevron-right text-[9px]"></i></button><button type="button" class="delete-link flex h-6 w-6 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50"><i class="fa-solid fa-trash text-[9px]"></i></button></div>';
        
        row.querySelector('p').textContent = link.title;
        row.querySelector('.toggle-link').addEventListener('click', () => toggleLink(row));
        row.querySelector('.edit-link').addEventListener('click', () => openLinkModal({ id: link.id, type: meta.type, url: link.url }));
        row.querySelector('.delete-link').addEventListener('click', () => deleteLink(row));
        
        bindMoveButtons(row);
        bindDrag(row);
        linksManager.appendChild(row);
        ensureEmpty();
    }

    function updateRow(row, link) {
        const meta = metaByIcon[link.icon] || {
            type: 'custom',
            icon: 'fa-solid fa-link',
            bg: 'bg-slate-100',
            text: 'text-slate-700'
        };
        row.dataset.active = link.is_active;
        row.dataset.title = link.title;
        row.dataset.url = link.url;
        row.dataset.icon = link.icon;
        row.classList.toggle('opacity-55', Number(link.is_active) !== 1);
        
        const title = row.querySelector('p');
        if (title) title.textContent = link.title;
        
        const iconBox = row.querySelector('.link-icon');
        if (iconBox) {
            iconBox.className = 'link-icon mt-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ' + meta.bg + ' ' + meta.text;
            const icon = iconBox.querySelector('i');
            if (icon) icon.className = meta.icon + ' text-lg';
        }
        
        row.querySelector('.toggle-link i').className = 'fa-solid ' + (Number(link.is_active) ? 'fa-eye' : 'fa-eye-slash') + ' text-[10px]';
        const edit = row.querySelector('.edit-link');
        if (edit) {
            edit.dataset.type = meta.type || '';
            edit.dataset.url = link.url;
        }
    }

    linkForm?.addEventListener('submit', e => {
        e.preventDefault();
        const data = {
            action: 'save_link',
            id: linkId.value,
            link_type: linkType.value,
            link_url: linkUrl.value
        };
        post(data).then(j => {
            if (!j.success) throw new Error(j.message);
            const link = j.link;
            if (!link) throw new Error('Data tautan tidak dikembalikan server.');
            const old = linksManager.querySelector('.link-row[data-id="' + link.id + '"]');
            if (old) updateRow(old, link);
            else addRow(link);
            closeLinkModal();
            syncPublicLinks();
            toast(j.message);
        }).catch(err => toast(err.message || 'Gagal menyimpan tautan.', true));
    });

    function toggleLink(row) {
        const next = Number(row.dataset.active) === 1 ? 0 : 1;
        post({ action: 'toggle_link', id: row.dataset.id, is_active: next }).then(j => {
            if (!j.success) throw new Error(j.message);
            row.dataset.active = next;
            row.classList.toggle('opacity-60', next !== 1);
            row.querySelector('.toggle-link i').className = 'fa-solid ' + (next ? 'fa-eye' : 'fa-eye-slash');
            syncPublicLinks();
            toast(j.message);
        }).catch(err => toast(err.message, true));
    }

    function deleteLink(row) {
        if (!confirm('Hapus tautan ini?')) return;
        post({ action: 'delete_link', id: row.dataset.id }).then(j => {
            if (!j.success) throw new Error(j.message);
            row.remove();
            ensureEmpty();
            syncPublicLinks();
            toast(j.message);
        }).catch(err => toast(err.message, true));
    }

    function moveLink(row, direction) {
        const target = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
        if (!target || !target.classList.contains('link-row')) return;
        if (direction === 'up') target.before(row);
        else target.after(row);
        saveOrder();
        syncPublicLinks();
    }

    function bindMoveButtons(row) {
        row.querySelector('.move-link-up')?.addEventListener('click', () => moveLink(row, 'up'));
        row.querySelector('.move-link-down')?.addEventListener('click', () => moveLink(row, 'down'));
    }

    document.querySelectorAll('.toggle-link').forEach(b => b.addEventListener('click', () => toggleLink(b.closest('.link-row'))));
    document.querySelectorAll('.delete-link').forEach(b => b.addEventListener('click', () => deleteLink(b.closest('.link-row'))));
    document.querySelectorAll('.link-row').forEach(bindMoveButtons);

    if (linksManager) {
        linksManager.querySelectorAll('.link-row').forEach(row => {
            row.dataset.title = row.querySelector('p')?.textContent || '';
            row.dataset.url = row.dataset.url || '';
            row.dataset.icon = row.querySelector('.link-icon i')?.className || '';
            bindDrag(row);
        });
    }

    function bindDrag(row) {
        if (row.dataset.dragBound === '1') return;
        row.dataset.dragBound = '1';
        row.addEventListener('dragstart', function () {
            window.__drag = row;
            row.classList.add('opacity-50', 'scale-95');
        });
        row.addEventListener('dragend', function () {
            row.classList.remove('opacity-50', 'scale-95');
            window.__drag = null;
            syncPublicLinks();
            saveOrder();
        });
        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            const d = window.__drag;
            if (!d || d === row) return;
            const r = row.getBoundingClientRect();
            if (e.clientX > r.left + r.width / 2) row.after(d);
            else row.before(d);
        });
        const handle = row.querySelector('.drag-handle');
        handle?.addEventListener('pointerdown', function (e) {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            const startX = e.clientX;
            const startY = e.clientY;
            let dragging = false;
            const onMove = function (ev) {
                const dx = ev.clientX - startX, dy = ev.clientY - startY;
                if (!dragging && Math.abs(dx) < 8) return;
                dragging = true;
                row.classList.add('opacity-60', 'scale-95');
                const els = [...linksManager.querySelectorAll('.link-row')].filter(x => x !== row);
                const target = els.find(el => {
                    const r = el.getBoundingClientRect();
                    return ev.clientX >= r.left && ev.clientX <= r.right;
                });
                if (target) {
                    const r = target.getBoundingClientRect();
                    if (ev.clientX > r.left + r.width / 2) target.after(row);
                    else target.before(row);
                }
                ev.preventDefault();
            };
            const onUp = function () {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
                row.classList.remove('opacity-60', 'scale-95');
                if (dragging) {
                    syncPublicLinks();
                    saveOrder();
                }
            };
            document.addEventListener('pointermove', onMove, { passive: false });
            document.addEventListener('pointerup', onUp, { once: true });
        });
    }

    let orderTimer;
    function saveOrder() {
        clearTimeout(orderTimer);
        if (!linksManager) return;
        const ids = [...linksManager.querySelectorAll('.link-row')].map(r => r.dataset.id);
        if (!ids.length) return;
        orderTimer = setTimeout(() => {
            post({ action: 'reorder_links', order: ids }).then(j => {
                if (!j.success) throw new Error(j.message || 'Urutan gagal disimpan.');
                syncPublicLinks();
                toast('Urutan tautan diperbarui.');
            }).catch(err => toast(err.message || 'Urutan gagal disimpan.', true));
        }, 120);
    }

    document.getElementById('open-links-manager')?.addEventListener('click', () => openSheet('links'));
    syncPublicLinks();
})();