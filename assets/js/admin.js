/* global jQuery, ClipboardJS, QRCode, Chart, wcRewardPoints */
(function($) {
    'use strict';

    // Initialize tooltips
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            $(this).attr('aria-label', $(this).data('tooltip'));
        });
    }

    // Initialize copy URL functionality
    function initCopyUrl() {
        const clipboard = new ClipboardJS('.copy-url');
        clipboard.on('success', function() {
            const $button = $('.copy-url');
            const originalText = $button.text();
            $button.text(wcRewardPoints.i18n.copied);
            setTimeout(() => {
                $button.text(originalText);
            }, 2000);
        });
    }

    // Initialize QR code
    function initQRCode() {
        const qrCode = new QRCode('signup-qr', {
            text: $('#signup-url').val(),
            width: 100,
            height: 100,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });

        // Add download QR code functionality
        $('#signup-qr').on('click', function() {
            const canvas = $(this).find('canvas')[0];
            const link = document.createElement('a');
            link.download = 'signup-qr.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });

        // Add tooltip
        $('#signup-qr').attr('data-tooltip', 'Click to download QR code');
    }

    // Initialize claims table functionality
    function initClaimsTable() {
        const $table = $('.claims-table');
        const $searchInput = $('#claims-search');
        const $filterSelect = $('#claims-filter');
        const $exportButton = $('.export-csv');
        const $tbody = $table.find('tbody');
        const $noResults = $('<tr><td colspan="4" class="no-results">' + wcRewardPoints.i18n.noResults + '</td></tr>');

        // Search functionality
        $searchInput.on('input', function() {
            const searchText = $(this).val().toLowerCase();
            let hasResults = false;

            $tbody.find('tr').each(function() {
                const $row = $(this);
                const rowText = $row.text().toLowerCase();
                const show = rowText.includes(searchText);
                $row.toggle(show);
                if (show) hasResults = true;
            });

            if (!hasResults) {
                if (!$tbody.find('.no-results').length) {
                    $tbody.append($noResults);
                }
            } else {
                $tbody.find('.no-results').remove();
            }
        });

        // Filter functionality
        $filterSelect.on('change', function() {
            const filter = $(this).val();
            const today = new Date();
            let hasResults = false;

            $tbody.find('tr').each(function() {
                const $row = $(this);
                const claimDate = new Date($row.find('td:nth-child(3)').text());
                let show = true;

                switch(filter) {
                    case 'today':
                        show = claimDate.toDateString() === today.toDateString();
                        break;
                    case 'week':
                        const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                        show = claimDate >= weekAgo;
                        break;
                    case 'month':
                        show = claimDate.getMonth() === today.getMonth() &&
                               claimDate.getFullYear() === today.getFullYear();
                        break;
                }

                $row.toggle(show);
                if (show) hasResults = true;
            });

            if (!hasResults) {
                if (!$tbody.find('.no-results').length) {
                    $tbody.append($noResults);
                }
            } else {
                $tbody.find('.no-results').remove();
            }
        });

        // Export functionality
        $exportButton.on('click', function() {
            const headers = [];
            $table.find('thead th').each(function() {
                headers.push($(this).text().trim());
            });

            const rows = [headers.join(',')];
            $table.find('tbody tr:visible').each(function() {
                const row = [];
                $(this).find('td').each(function() {
                    let text = $(this).text().trim().replace(/"/g, '""');
                    row.push(`"${text}"`);
                });
                rows.push(row.join(','));
            });

            const csv = rows.join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', wcRewardPoints.i18n.exportFileName + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // Sort functionality
        $table.find('thead th').each(function(index) {
            $(this).on('click', function() {
                const $th = $(this);
                const isAsc = $th.hasClass('asc');
                
                // Remove sort classes from all headers
                $table.find('th').removeClass('asc desc');
                
                // Add sort class to clicked header
                $th.addClass(isAsc ? 'desc' : 'asc');

                // Sort rows
                const rows = $tbody.find('tr').get();
                rows.sort(function(a, b) {
                    const aText = $(a).find('td').eq(index).text();
                    const bText = $(b).find('td').eq(index).text();
                    
                    if (index === 1) { // Points column - numeric sort
                        return isAsc ? 
                            parseInt(aText) - parseInt(bText) : 
                            parseInt(bText) - parseInt(aText);
                    } else { // Text sort
                        return isAsc ? 
                            aText.localeCompare(bText) : 
                            bText.localeCompare(aText);
                    }
                });

                // Reattach sorted rows
                $.each(rows, function(index, row) {
                    $tbody.append(row);
                });
            });
        });
    }

    // Initialize settings validation
    function initSettingsValidation() {
        const $form = $('.settings-card form');
        const $submitButton = $form.find(':submit');

        $form.on('submit', function(e) {
            const $inputs = $form.find('input[required]');
            let isValid = true;

            $inputs.each(function() {
                const $input = $(this);
                const $group = $input.closest('.reward-points-input-group');
                const value = $input.val();
                
                if (!value || (
                    $input.attr('type') === 'number' && 
                    (
                        (typeof $input.attr('min') !== 'undefined' && parseInt(value) < parseInt($input.attr('min'))) ||
                        (typeof $input.attr('max') !== 'undefined' && parseInt(value) > parseInt($input.attr('max')))
                    )
                )) {
                    isValid = false;
                    $group.addClass('has-error');
                    if (!$group.find('.error-message').length) {
                        $group.append('<span class="error-message">' + 
                            ($input.data('error') || 'This field is required') + '</span>');
                    }
                } else {
                    $group.removeClass('has-error').find('.error-message').remove();
                }
            });

            if (!isValid) {
                e.preventDefault();
                $submitButton.addClass('shake');
                setTimeout(() => $submitButton.removeClass('shake'), 1000);
            }
        });
    }

    // Document ready
    $(function() {
        initTooltips();
        initCopyUrl();
        initQRCode();
        initClaimsTable();
        initSettingsValidation();
    });

})(jQuery); 