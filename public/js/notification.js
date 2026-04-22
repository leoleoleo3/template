// Notification Function with Redirect Option
function showNotification(message, type, redirectUrl = null) {
    const container = document.getElementById('notification-container');
    const icons = {
        success: '<i class="fas fa-check-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>',
        danger: '<i class="fas fa-times-circle"></i>'
    };

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        ${icons[type]}
        <div class="message">${message}</div>
        <button class="close-btn" onclick="this.parentElement.remove()">×</button>
    `;

    // Add to DOM
    container.appendChild(notification);
    setTimeout(() => notification.classList.add('show'), 100);

    // Auto-remove after 5 seconds and redirect if needed
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
            if (redirectUrl) {
                window.location.href = redirectUrl;
            }
        }, 300);
    }, 5000);
}

// Handle Form Submission
$('#dateForm').on('submit', function (e) {
    e.preventDefault(); // prevent page refresh

    $.ajax({
        url: 'save.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function (response) {
            if (response.NoError === false) {
                showNotification(response.Message, 'success', 'dashboard.php');
            } else {
                showNotification(response.Message, 'danger');
            }
        },
        error: function () {
            showNotification("An unexpected error occurred.", 'danger');
        }
    });
});
