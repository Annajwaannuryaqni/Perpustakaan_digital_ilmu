document.addEventListener('DOMContentLoaded', function () {
    const bellWrapper = document.getElementById('notifBellWrapper');
    const bellIcon = document.getElementById('notifBellIcon');
    const bellBadge = document.getElementById('notifBadge');
    const dropdown = document.getElementById('notifDropdown');
    const notifListContainer = document.getElementById('notifListContainer');
    const unreadBadgeCount = document.getElementById('unreadBadgeCount');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    
    const soundToggleBtn = document.getElementById('soundToggleBtn');
    const soundIcon = document.getElementById('soundIcon');
    const notificationSound = document.getElementById('notificationSound');

    let soundEnabled = localStorage.getItem('soundEnabled') !== 'false';
    updateSoundIcon();

    // 1. Minta Izin Browser Notification API
    if (window.Notification && Notification.permission !== "granted") {
        Notification.requestPermission();
    }

  // Toggle Sound Setting
    soundToggleBtn.addEventListener('click', () => {
        soundEnabled = !soundEnabled;
        localStorage.setItem('soundEnabled', soundEnabled);
        updateSoundIcon();
        showToast("Pengaturan Suara", soundEnabled ? "Suara notifikasi diaktifkan" : "Suara notifikasi dimatikan", "info", "fa-volume-up", "#4facfe");
    });

    function updateSoundIcon() {
        if (soundEnabled) {
            soundIcon.className = "fas fa-volume-up";
        } else {
            soundIcon.className = "fas fa-volume-mute";
        }
    }

    // Toggle Dropdown Lonceng
    bellWrapper.addEventListener('click', function (e) {
        e.stopPropagation();
        if (dropdown.style.display === 'flex') {
            dropdown.style.display = 'none';
        } else {
            dropdown.style.display = 'flex';
            fetchNotifications(); // Refresh saat dibuka
        }
    });

    document.addEventListener('click', function () {
        dropdown.style.display = 'none';
    });

    dropdown.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    let lastUnreadCount = 0;

    // Polling AJAX untuk mengecek notifikasi baru setiap 5 detik
    function fetchNotifications() {
        fetch('../api/notifications.php?action=get')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    renderNotifications(data.notifications, data.unread_count);

                    // Deteksi jika ada notifikasi baru masuk
                    if (data.unread_count > lastUnreadCount && lastUnreadCount !== -1) {
                        // Ambil notifikasi teratas yang belum dibaca untuk toast & browser notification
                        const latest = data.notifications.find(n => n.is_read == 0);
                        if (latest) {
                            triggerAlertEffects(latest);
                        }
                    }
                    lastUnreadCount = data.unread_count;
                }
            })
            .catch(err => console.error('Gagal memuat notifikasi:', err));
    }

    // Panggil pertama kali dan set interval (5 detik)
    fetchNotifications();
    setInterval(fetchNotifications, 5000);

    function renderNotifications(notifications, unreadCount) {
        // Update Badge Lonceng & Animasi Shake
        if (unreadCount > 0) {
            bellBadge.style.display = 'inline-block';
            bellBadge.innerText = unreadCount > 99 ? '99+' : unreadCount;
            bellIcon.classList.add('shake');
            unreadBadgeCount.innerText = `${unreadCount} belum dibaca`;
        } else {
            bellBadge.style.display = 'none';
            bellIcon.classList.remove('shake');
            unreadBadgeCount.innerText = '0 belum dibaca';
        }

        // Render List Dropdown
        if (notifications.length === 0) {
            notifListContainer.innerHTML = `<div class="text-center p-3 text-muted small">Tidak ada notifikasi</div>`;
            return;
        }

        let html = '';
        notifications.forEach(n => {
            const unreadClass = n.is_read == 0 ? 'unread' : '';
            html += `
                <div class="notif-item ${unreadClass}" data-id="${n.id}">
                    <div class="notif-icon-box" style="background-color: ${n.color || '#00f2fe'};">
                        <i class="fas ${n.icon || 'fa-info-circle'}"></i>
                    </div>
                    <div class="notif-content">
                        <h4>${n.title}</h4>
                        <p>${n.message}</p>
                        <span class="notif-time">${n.time_ago}</span>
                    </div>
                </div>
            `;
        });
        notifListContainer.innerHTML = html;

        // Event klik pada item notifikasi individual
        document.querySelectorAll('.notif-item').forEach(item => {
            item.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                markOneAsRead(id);
            });
        });
    }

    function triggerAlertEffects(notif) {
        // 1. Toast Notification
        showToast(notif.title, notif.message, notif.type, notif.icon, notif.color);

        // 2. Sound Notification
        if (soundEnabled) {
            notificationSound.currentTime = 0;
            notificationSound.play().catch(e => console.log("Audio play blocked by browser policy"));
        }

        // 3. Browser Desktop Notification
        if (window.Notification && Notification.permission === "granted") {
            new Notification(notif.title, {
                body: notif.message,
                icon: '../assets/img/logo.png' // Sesuaikan path logo
            });
        }
    }

    // Fungsi Tampil Toast
    window.showToast = function(title, message, type = 'info', icon = 'fa-info-circle', color = '#00f2fe') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = 'toast-modern';
        
        toast.innerHTML = `
            <div class="toast-icon" style="color: ${color};"><i class="fas ${icon}"></i></div>
            <div class="toast-body">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close">&times;</button>
            <div class="toast-progress" style="background: ${color};"></div>
        `;

        container.appendChild(toast);

        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            removeToast(toast);
        });

        // Hilangkan otomatis setelah 5 detik
        const timer = setTimeout(() => {
            removeToast(toast);
        }, 5000);

        toast.addEventListener('mouseenter', () => clearTimeout(timer));
    };

    function removeToast(toast) {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 400);
    }

    // Tombol Aksi: Tandai Semua Dibaca
    markAllReadBtn.addEventListener('click', function () {
        fetch('../api/notifications.php?action=read_all')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchNotifications();
                }
            });
    });

    // Tombol Aksi: Hapus Semua
    clearAllBtn.addEventListener('click', function () {
        fetch('../api/notifications.php?action=clear_all')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchNotifications();
                }
            });
    });

    function markOneAsRead(id) {
        let formData = new FormData();
        formData.append('id', id);
        fetch('../api/notifications.php?action=read_one', {
            method: 'POST',
            body: formData
        }).then(() => {
            fetchNotifications();
        });
    }
});