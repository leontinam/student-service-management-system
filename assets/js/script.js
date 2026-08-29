function initialisePageControls() {

    function updateNotificationIndicators(count) {
        const displayCount = count > 99 ? '99+' : String(count);
        const topNotification = document.querySelector('.app-topbar-action-btn[aria-label="Notifications"]');
        const sidebarNotification = Array.from(document.querySelectorAll('.app-sidebar a'))
            .find(function (link) { return link.textContent.trim().startsWith('Notifications'); });

        [topNotification, sidebarNotification].forEach(function (notificationLink) {
            if (!notificationLink) return;

            let badge = notificationLink.querySelector('.notification-count, .notification-sidebar-count, .badge');
            if (count <= 0) {
                if (badge) badge.remove();
                return;
            }

            if (!badge) {
                badge = document.createElement('span');
                badge.className = notificationLink === topNotification ? 'notification-count' : 'badge bg-danger notification-sidebar-count';
                notificationLink.appendChild(badge);
            }
            badge.textContent = displayCount;
            notificationLink.setAttribute('aria-label', 'Notifications (' + displayCount + ' unread)');
        });
    }

    fetch('../api/notification_count.php')
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (data) {
            if (data && typeof data.count === 'number') updateNotificationIndicators(data.count);
        })
        .catch(function () { /* Notification badges remain unchanged if the count cannot be loaded. */ });

    const deleteLinks = document.querySelectorAll('.confirm-delete');
    deleteLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            const confirmed = confirm('Are you sure you want to delete this?');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    });

    const themeToggle = document.getElementById('theme-toggle');
    const savedTheme = localStorage.getItem('ssms-theme');

    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        document.documentElement.classList.add('dark-mode');
        if (themeToggle) {
            themeToggle.classList.add('is-dark');
            themeToggle.setAttribute('aria-label', 'Switch to light mode');
        }
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            document.documentElement.classList.toggle('dark-mode', isDark);
            localStorage.setItem('ssms-theme', isDark ? 'dark' : 'light');
            themeToggle.classList.toggle('is-dark', isDark);
            themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        });
    }

    const profileButton = document.querySelector('.app-profile-btn');
    if (profileButton) {
        function applyProfilePicture(user) {
            if (user.profile_picture) {
                profileButton.innerHTML = `<img src="../${user.profile_picture}" alt="">`;
            } else {
                profileButton.classList.add('app-profile-default');
            }
        }

        fetch('../profile_data.php')
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (user) {
                if (user && !user.error) applyProfilePicture(user);
            })
            .catch(function () { /* Keep the initial visible if the profile image cannot be loaded. */ });

        profileButton.addEventListener('click', async function () {
            let modal = document.querySelector('.profile-modal-backdrop');
            if (modal) { modal.remove(); return; }
            try {
                const response = await fetch('../profile_data.php');
                const user = await response.json();
                if (!response.ok || user.error) return;
                modal = document.createElement('div');
                modal.className = 'profile-modal-backdrop';
                const avatar = user.profile_picture
                    ? `<img src="../${user.profile_picture}" alt="">`
                    : `<span class="profile-modal-default-avatar" aria-hidden="true">👤</span>`;
                modal.innerHTML = `<section class="profile-modal" role="dialog" aria-modal="true" aria-labelledby="profile-modal-name"><button type="button" class="profile-modal-close" aria-label="Close profile">&times;</button><div class="profile-modal-avatar">${avatar}</div><h2 id="profile-modal-name" class="profile-modal-name"></h2><p class="profile-modal-role"></p><div class="profile-modal-details"><div><span>Email</span><strong></strong></div><div><span>Phone</span><strong></strong></div></div><div class="profile-modal-actions"><a class="profile-modal-manage" href="../profile.php">Manage profile picture</a><a class="profile-modal-password" href="../change_password.php">Change password</a><a class="profile-modal-logout" href="../auth/logout.php">Logout</a></div></section>`;
                modal.querySelector('.profile-modal-name').textContent = user.full_name;
                const details = modal.querySelectorAll('.profile-modal-details strong');
                details[0].textContent = user.email;
                details[1].textContent = user.phone_number;
                modal.querySelector('.profile-modal-role').textContent = (user.role || 'user').replace(/[_-]/g, ' ');
                applyProfilePicture(user);
                modal.querySelector('.profile-modal-close').addEventListener('click', () => modal.remove());
                modal.addEventListener('click', (event) => { if (event.target === modal) modal.remove(); });
                document.body.appendChild(modal);
            } catch (error) { /* Keep the control unobtrusive if the profile endpoint is unavailable. */ }
        });
    }

    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.querySelector('.app-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const sidebarCloseBtn = document.querySelector('.app-sidebar-close-btn');

    if (hamburgerBtn && sidebar && overlay) {
        hamburgerBtn.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });

        if (sidebarCloseBtn) {
            sidebarCloseBtn.addEventListener('click', function () {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            });
        }
    }

}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialisePageControls);
} else {
    initialisePageControls();
}
