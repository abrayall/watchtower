        var currentFilter = null;

        function filterSites(filterType) {
            var $ = jQuery;

            if (currentFilter === filterType) {
                currentFilter = null;
                $('.filter-card').removeClass('filter-active');
                $('.site-row').show();
                return;
            }

            currentFilter = filterType;

            $('.filter-card').removeClass('filter-active');
            $('.filter-card[data-filter="' + filterType + '"]').addClass('filter-active');

            if (filterType === 'all') {
                $('.site-row').show();
                return;
            }

            $('.site-row').each(function() {
                var healthStatus = $(this).data('health-status');

                if (filterType === 'healthy') {
                    if (healthStatus === 'healthy') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                } else if (filterType === 'unhealthy') {
                    if (healthStatus === 'warning' || healthStatus === 'critical') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                }
            });
        }

        jQuery(document).ready(function($) {
            $('.clickable-row').on('click', function(e) {
                if ($(e.target).closest('a, button').length) {
                    return;
                }

                var detailsUrl = $(this).data('details-url');
                if (detailsUrl) {
                    window.location.href = detailsUrl;
                }
            });

            $('.clickable-row').on('mouseenter', function(e) {
                if (!$(e.target).closest('a, button').length) {
                    $(this).css('cursor', 'pointer');
                }
            });

            $('.scan-site').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var siteUrl = button.data('site');
                var originalText = button.text();

                button.text('Scanning...').prop('disabled', true);

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_scan_agent',
                    site: siteUrl,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success) {
                        button.text('Done!');
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        alert('Scan failed: ' + response.data.message);
                        button.text(originalText).prop('disabled', false);
                    }
                });
            });

            $('.remove-site').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var siteUrl = button.data('site');

                if (!confirm('Are you sure you want to remove this site from the manager?')) {
                    return;
                }

                button.text('Removing...').prop('disabled', true);

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_remove_agent',
                    site: siteUrl,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut(function() {
                            $(this).remove();
                            if ($('.sites-table tbody tr').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert('Failed to remove site: ' + response.data.message);
                        button.text('Remove').prop('disabled', false);
                    }
                });
            });

            $('.copy-credentials-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Prevent row click
                var button = $(this);
                var siteUrl = button.data('site');
                var username = button.data('username');
                var password = button.data('password');

                var credentials = 'url: ' + siteUrl + ', user: ' + username + ', password: ' + password;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(credentials).then(function() {
                        var icon = button.find('.dashicons');
                        var originalColor = icon.css('color');
                        icon.css('color', '#00a32a');
                        setTimeout(function() {
                            icon.css('color', originalColor);
                        }, 1000);
                    }).catch(function(err) {
                        console.error('Failed to copy credentials: ', err);
                    });
                } else {
                    var textarea = document.createElement('textarea');
                    textarea.value = credentials;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        var icon = button.find('.dashicons');
                        var originalColor = icon.css('color');
                        icon.css('color', '#00a32a');
                        setTimeout(function() {
                            icon.css('color', originalColor);
                        }, 1000);
                    } catch (err) {
                        console.error('Failed to copy credentials: ', err);
                    }
                    document.body.removeChild(textarea);
                }
            });

            setTimeout(function() {
                $('#watchtower-dashboard-loading').fadeOut(300, function() {
                    $(this).remove();
                });
                $('#watchtower-dashboard-content').css('opacity', '1');
            }, 500);
        });
