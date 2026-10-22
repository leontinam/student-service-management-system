function initialisePageControls() {
    let csrfToken = null;

    // Dismiss transient feedback after a short delay while leaving empty-notification states visible.
    document.querySelectorAll('.alert:not(.notification-empty)').forEach(function (alert) {
        window.setTimeout(function () {
            alert.classList.add('alert-auto-dismissing');
            window.setTimeout(function () { alert.remove(); }, 250);
        }, 4000);
    });

    // Keep the top-bar and sidebar unread badges in sync with the notification API response.
    function updateNotificationIndicators(count) {
        const displayCount = count > 99 ? '99+' : String(count);
        const topNotification = document.querySelector('.app-topbar-action-btn[aria-label^="Notifications"]');
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

    const csrfReady = fetch('../api/notification_count.php')
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (data) {
            if (data && typeof data.csrf_token === 'string') csrfToken = data.csrf_token;
            if (data && typeof data.count === 'number') updateNotificationIndicators(data.count);
            return csrfToken;
        })
        .catch(function () { /* Notification badges remain unchanged if the count cannot be loaded. */ return null; });

    // Mark a notice read through the API when activated by mouse or keyboard.
    document.querySelectorAll('[data-notification-id]').forEach(function (notification) {
        const markAsRead = async function () {
            if (notification.classList.contains('notification-read')) return;

            const requestToken = csrfToken || await csrfReady;
            if (!requestToken) return;
            const body = new URLSearchParams({ notification_id: notification.dataset.notificationId });
            fetch('../api/notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-Token': requestToken },
                body: body.toString()
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (!data || typeof data.count !== 'number') return;
                    notification.classList.remove('notification-unread');
                    notification.classList.add('notification-read');
                    notification.setAttribute('aria-label', 'Read notification');
                    updateNotificationIndicators(data.count);
                })
                .catch(function () { /* Leave the item unread if the request fails. */ });
        };

        notification.addEventListener('click', markAsRead);
        notification.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                markAsRead();
            }
        });
    });

    const deleteLinks = document.querySelectorAll('.confirm-delete');
    deleteLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            const confirmed = confirm('Are you sure you want to delete this?');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    });

    // Restore the saved theme and persist later changes in local storage.
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

    // Load profile data on demand and submit edits, including an optional image, to the PHP endpoints.
    const profileButton = document.querySelector('.app-profile-btn');
    if (profileButton) {
        function applyProfilePicture(user) {
            if (user.profile_picture) {
                profileButton.innerHTML = `<img src="../${user.profile_picture}" alt="">`;
                profileButton.classList.remove('app-profile-default');
            } else {
                profileButton.classList.add('app-profile-default');
            }
        }

        function profileAvatar(user) {
            return user.profile_picture
                ? `<img src="../${user.profile_picture}" alt="">`
                : `<span class="profile-modal-default-avatar" aria-hidden="true">👤</span>`;
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
                let user = await response.json();
                if (!response.ok || user.error) return;
                modal = document.createElement('div');
                modal.className = 'profile-modal-backdrop';
                csrfToken = user.csrf_token || csrfToken;
                modal.innerHTML = `<section class="profile-modal" role="dialog" aria-modal="true" aria-labelledby="profile-modal-name"><button type="button" class="profile-modal-close" aria-label="Close profile">&times;</button><div class="profile-view"><div class="profile-modal-avatar"></div><h2 id="profile-modal-name" class="profile-modal-name"></h2><p class="profile-modal-role"></p><div class="profile-modal-details"><div data-profile-field="email"><span>Email</span><strong></strong></div><div data-profile-field="student-number"><span>Student Number</span><strong></strong></div><div data-profile-field="phone"><span>Phone</span><strong></strong></div></div><div class="profile-modal-actions"><button type="button" class="profile-modal-manage">Edit Profile</button><a class="profile-modal-password" href="../change_password.php">Change password</a><form action="../auth/logout.php" method="post"><input type="hidden" name="csrf_token" value="${csrfToken || ''}"><button type="submit" class="profile-modal-logout">Logout</button></form></div></div><form class="profile-edit-form" enctype="multipart/form-data" hidden><div class="profile-edit-avatar"></div><label class="form-label" for="profile-picture">Profile picture</label><input class="form-control" id="profile-picture" type="file" name="profile_picture" accept="image/*"><label class="form-label" for="profile-full-name">Full name</label><input class="form-control" id="profile-full-name" type="text" name="full_name" maxlength="100" required><label class="form-label" for="profile-phone-number">Phone number</label><input class="form-control" id="profile-phone-number" type="text" name="phone_number" inputmode="numeric" pattern="\\d{10}" maxlength="10" required><label class="form-label" for="profile-email">Email</label><input class="form-control" id="profile-email" type="email" disabled><label class="form-label" for="profile-student-number">Student number</label><input class="form-control" id="profile-student-number" type="text" disabled><p class="profile-edit-feedback" aria-live="polite" hidden></p><div class="profile-edit-actions"><button type="submit" class="btn btn-brand">Save Changes</button><button type="button" class="btn btn-outline-secondary profile-edit-cancel">Cancel</button></div></form></section>`;

                const view = modal.querySelector('.profile-view');
                const editForm = modal.querySelector('.profile-edit-form');
                const setProfileValues = function (profile) {
                    modal.querySelector('.profile-modal-avatar').innerHTML = profileAvatar(profile);
                    modal.querySelector('.profile-edit-avatar').innerHTML = profileAvatar(profile);
                    modal.querySelector('.profile-modal-name').textContent = profile.full_name;
                    modal.querySelector('[data-profile-field="email"] strong').textContent = profile.email;
                    modal.querySelector('[data-profile-field="student-number"] strong').textContent = profile.student_number || 'Not assigned';
                    modal.querySelector('[data-profile-field="phone"] strong').textContent = profile.phone_number;
                    modal.querySelector('.profile-modal-role').textContent = (profile.role || 'user').replace(/[_-]/g, ' ');
                    editForm.elements.full_name.value = profile.full_name;
                    editForm.elements.phone_number.value = profile.phone_number;
                    editForm.querySelector('#profile-email').value = profile.email;
                    editForm.querySelector('#profile-student-number').value = profile.student_number || 'Not assigned';
                };

                setProfileValues(user);
                applyProfilePicture(user);
                modal.querySelector('.profile-modal-close').addEventListener('click', () => modal.remove());
                modal.addEventListener('click', (event) => { if (event.target === modal) modal.remove(); });
                modal.querySelector('.profile-modal-manage').addEventListener('click', function () {
                    view.hidden = true;
                    editForm.hidden = false;
                    editForm.elements.full_name.focus();
                });
                modal.querySelector('.profile-edit-cancel').addEventListener('click', function () {
                    editForm.reset();
                    setProfileValues(user);
                    editForm.hidden = true;
                    view.hidden = false;
                });
                editForm.querySelector('#profile-picture').addEventListener('change', function () {
                    const picture = this.files[0];
                    if (!picture) return;
                    const preview = editForm.querySelector('.profile-edit-avatar');
                    preview.innerHTML = '';
                    const image = document.createElement('img');
                    image.src = URL.createObjectURL(picture);
                    image.alt = 'New profile picture preview';
                    image.onload = function () { URL.revokeObjectURL(image.src); };
                    preview.appendChild(image);
                });
                editForm.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    const feedback = editForm.querySelector('.profile-edit-feedback');
                    const saveButton = editForm.querySelector('[type="submit"]');
                    feedback.hidden = true;
                    saveButton.disabled = true;
                    try {
                        const profileFormData = new FormData(editForm);
                        profileFormData.set('csrf_token', csrfToken || '');
                        const updateResponse = await fetch('../update-profile.php', { method: 'POST', body: profileFormData });
                        const result = await updateResponse.json();
                        if (!updateResponse.ok || !result.user) throw new Error(result.error || 'Your profile could not be updated.');
                        user = result.user;
                        setProfileValues(user);
                        applyProfilePicture(user);
                        editForm.hidden = true;
                        view.hidden = false;
                    } catch (error) {
                        feedback.textContent = error.message || 'Your profile could not be updated.';
                        feedback.hidden = false;
                    } finally {
                        saveButton.disabled = false;
                    }
                });
                document.body.appendChild(modal);
            } catch (error) { /* Keep the control unobtrusive if the profile endpoint is unavailable. */ }
        });
    }

    // The overlay and close control share the same state as the responsive sidebar.
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
