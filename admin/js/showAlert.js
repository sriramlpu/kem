function showAlert(message, type) {
    $("#alert-container .bootstrap-alert").remove();
    let alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show bootstrap-alert shadow" role="alert" style="min-width:240px; margin-bottom:12px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $("#alert-container").append(alertHtml);
    setTimeout(function () {
        $("#alert-container .bootstrap-alert").alert("close");
    }, 3000);
}