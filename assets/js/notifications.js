/**
 * Real-time Notifications using SSE
 */

document.addEventListener('DOMContentLoaded', function () {
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationList = document.getElementById('notificationList');
    const markAllReadBtn = document.getElementById('markAllRead');
    let lastId = 0;

    if (!notificationBadge) return;

    // Initialize SSE
    function initSSE() {
        const eventSource = new EventSource(`${window.SITE_URL}/api/notifications_stream.php?last_id=${lastId}`);

        eventSource.onmessage = function (event) {
            const data = JSON.parse(event.data);
            if (data.id) {
                addNotificationToList(data, true);
                updateBadgeCount(1);
                lastId = data.id;

                // Optional: Show a browser notification if supported
                showBrowserNotification(data);
            }
        };

        eventSource.onerror = function (err) {
            console.error("SSE connection failed:", err);
            eventSource.close();
            // Retry after 5 seconds
            setTimeout(initSSE, 5000);
        };
    }

    function addNotificationToList(notification, isNew = false) {
        // Remove "No new notifications" message if it exists
        const emptyMsg = notificationList.querySelector('.text-muted');
        if (emptyMsg && emptyMsg.innerText.includes('No new notifications')) {
            notificationList.innerHTML = '';
        }

        const item = document.createElement('li');
        item.className = `notification-item ${notification.is_read == 0 ? 'unread' : ''}`;

        const iconClass = getIconForType(notification.type);
        const bgClass = getBgForType(notification.type);
        const timeAgo = formatTimeAgo(new Date(notification.created_at));

        item.innerHTML = `
            <a href="${notification.link || '#'}" class="text-decoration-none d-flex w-100">
                <div class="notification-icon ${bgClass} text-white">
                    <i class="bi ${iconClass}"></i>
                </div>
                <div class="notification-content">
                    <span class="notification-title">${notification.title}</span>
                    <p class="notification-message">${notification.message}</p>
                    <span class="notification-time">${timeAgo}</span>
                </div>
            </a>
        `;

        if (isNew) {
            notificationList.insertBefore(item, notificationList.firstChild);
        } else {
            notificationList.appendChild(item);
        }
    }

    function updateBadgeCount(change) {
        let currentCount = parseInt(notificationBadge.innerText) || 0;
        currentCount += change;

        if (currentCount > 0) {
            notificationBadge.innerText = currentCount > 99 ? '99+' : currentCount;
            notificationBadge.classList.remove('d-none');
        } else {
            notificationBadge.classList.add('d-none');
        }
    }

    function getIconForType(type) {
        switch (type) {
            case 'success': return 'bi-check-circle';
            case 'warning': return 'bi-exclamation-triangle';
            case 'danger': return 'bi-x-circle';
            default: return 'bi-info-circle';
        }
    }

    function getBgForType(type) {
        switch (type) {
            case 'success': return 'bg-success';
            case 'warning': return 'bg-warning';
            case 'danger': return 'bg-danger';
            default: return 'bg-primary';
        }
    }

    function formatTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        return "just now";
    }

    function showBrowserNotification(data) {
        if (!("Notification" in window)) return;

        if (Notification.permission === "granted") {
            new Notification(data.title, {
                body: data.message,
                icon: `${window.SITE_URL}/assets/images/logo.png`
            });
        } else if (Notification.permission !== "denied") {
            Notification.requestPermission();
        }
    }

    // Mark all as read
    markAllReadBtn.addEventListener('click', function (e) {
        e.preventDefault();
        fetch(`${window.SITE_URL}/api/mark_notifications_read.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.getElementById('global_csrf_token').value
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    notificationBadge.classList.add('d-none');
                    notificationBadge.innerText = '0';
                    const unreadItems = notificationList.querySelectorAll('.unread');
                    unreadItems.forEach(item => item.classList.remove('unread'));
                }
            });
    });

    // Initial load of existing unread notifications
    fetch(`${window.SITE_URL}/api/get_notifications.php`)
        .then(response => response.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                notificationList.innerHTML = '';
                data.notifications.forEach(n => {
                    addNotificationToList(n);
                    if (n.is_read == 0) updateBadgeCount(1);
                    if (n.id > lastId) lastId = n.id;
                });
            }
            // Start SSE after initial load
            initSSE();
        });
});
