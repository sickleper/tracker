(function(window, $) {
    const FuelPage = window.FuelPage = window.FuelPage || {};

    FuelPage.state = FuelPage.state || {
        anomaliesChartInstance: null,
        mplChartInstance: null,
        vehiclesData: []
    };

    FuelPage.escapeHtml = function(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    FuelPage.getSwalTheme = function() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    };

    FuelPage.openModal = function(id) {
        $(`#${id}`).removeClass('hidden');
        document.body.style.overflow = 'hidden';
    };

    FuelPage.closeModal = function(id) {
        $(`#${id}`).addClass('hidden');
        document.body.style.overflow = '';
    };

    FuelPage.validateMileage = function() {
        const start = parseFloat($('#start_mileage').val());
        const finish = parseFloat($('#finish_mileage').val());

        if (start > finish) {
            $('#error-message').text('⚠️ ODO ERROR: Finish mileage must be higher than start.');
            return false;
        }

        $('#error-message').text('');
        return true;
    };

    window.openModal = FuelPage.openModal;
    window.closeModal = FuelPage.closeModal;
    window.validateMileage = FuelPage.validateMileage;
    window.getSwalTheme = FuelPage.getSwalTheme;
})(window, jQuery);
