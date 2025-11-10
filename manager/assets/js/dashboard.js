        var currentFilter = null;

        function showAlert(message) {
            return new Promise(function(resolve) {
                var overlay = jQuery('<div class="watchtower-dialog-overlay"></div>');
                var dialog = jQuery('<div class="watchtower-dialog"></div>');
                var body = jQuery('<div class="watchtower-dialog-body"></div>').text(message);
                var footer = jQuery('<div class="watchtower-dialog-footer"></div>');
                var okButton = jQuery('<button class="watchtower-dialog-button primary">OK</button>');

                footer.append(okButton);
                dialog.append(body).append(footer);
                overlay.append(dialog);

                okButton.on('click', function() {
                    overlay.remove();
                    resolve();
                });

                overlay.on('click', function(e) {
                    if (jQuery(e.target).hasClass('watchtower-dialog-overlay')) {
                        overlay.remove();
                        resolve();
                    }
                });

                jQuery(document).on('keydown.watchtower-dialog', function(e) {
                    if (e.key === 'Escape' || e.key === 'Enter') {
                        jQuery(document).off('keydown.watchtower-dialog');
                        overlay.remove();
                        resolve();
                    }
                });

                jQuery('body').append(overlay);
                okButton.focus();
            });
        }

        function showConfirm(message) {
            return new Promise(function(resolve) {
                var overlay = jQuery('<div class="watchtower-dialog-overlay"></div>');
                var dialog = jQuery('<div class="watchtower-dialog"></div>');
                var body = jQuery('<div class="watchtower-dialog-body"></div>').html(message.replace(/\n/g, '<br>'));
                var footer = jQuery('<div class="watchtower-dialog-footer"></div>');
                var cancelButton = jQuery('<button class="watchtower-dialog-button secondary">Cancel</button>');
                var confirmButton = jQuery('<button class="watchtower-dialog-button primary">OK</button>');

                footer.append(confirmButton).append(cancelButton);
                dialog.append(body).append(footer);
                overlay.append(dialog);

                confirmButton.on('click', function() {
                    jQuery(document).off('keydown.watchtower-dialog');
                    overlay.remove();
                    resolve(true);
                });

                cancelButton.on('click', function() {
                    jQuery(document).off('keydown.watchtower-dialog');
                    overlay.remove();
                    resolve(false);
                });

                overlay.on('click', function(e) {
                    if (jQuery(e.target).hasClass('watchtower-dialog-overlay')) {
                        jQuery(document).off('keydown.watchtower-dialog');
                        overlay.remove();
                        resolve(false);
                    }
                });

                jQuery(document).on('keydown.watchtower-dialog', function(e) {
                    if (e.key === 'Escape') {
                        jQuery(document).off('keydown.watchtower-dialog');
                        overlay.remove();
                        resolve(false);
                    } else if (e.key === 'Enter') {
                        jQuery(document).off('keydown.watchtower-dialog');
                        overlay.remove();
                        resolve(true);
                    }
                });

                jQuery('body').append(overlay);
                confirmButton.focus();
            });
        }

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
                        showAlert('Scan failed: ' + response.data.message).then(function() {
                            button.text(originalText).prop('disabled', false);
                        });
                    }
                });
            });

            $('.remove-site').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var siteUrl = button.data('site');

                showConfirm('Are you sure you want to remove this site from the manager?').then(function(confirmed) {
                    if (!confirmed) {
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
                            showAlert('Failed to remove site: ' + response.data.message).then(function() {
                                button.text('Remove').prop('disabled', false);
                            });
                        }
                    });
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
