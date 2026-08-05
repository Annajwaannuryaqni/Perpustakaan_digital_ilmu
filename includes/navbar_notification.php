<!-- Integrasi Notification Bell di Navbar -->
<div class="d-flex align-items-center ms-auto">
    <!-- Pengaturan Suara ON/OFF -->
    <button id="soundToggleBtn" class="btn btn-sm text-light me-3" title="Toggle Sound Notification">
        <i id="soundIcon" class="fas fa-volume-up"></i>
    </button>

    <div class="notif-bell-wrapper" id="notifBellWrapper">
        <div class="notif-bell-icon" id="notifBellIcon">
            <i class="fas fa-bell"></i>
            <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
        </div>

        <!-- Dropdown Center -->
        <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-header">
                <span>Notifikasi</span>
                <span class="badge bg-primary" id="unreadBadgeCount">0 belum dibaca</span>
            </div>
            <div class="notif-body" id="notifListContainer">
                <!-- Konten dinamis dari AJAX -->
                <div class="text-center p-3 text-muted small">Memuat notifikasi...</div>
            </div>
            <div class="notif-footer">
                <button id="markAllReadBtn">Tandai Semua Dibaca</button>
                <button id="clearAllBtn">Hapus Semua</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- Audio element untuk Sound Notification -->
<audio id="notificationSound" src="../assets/sounds/notification.mp3" preload="auto"></audio>