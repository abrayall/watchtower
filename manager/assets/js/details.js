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

    function showPrompt(message, defaultValue) {
        return new Promise(function(resolve) {
            var overlay = jQuery('<div class="watchtower-dialog-overlay"></div>');
            var dialog = jQuery('<div class="watchtower-dialog"></div>');
            var body = jQuery('<div class="watchtower-dialog-body"></div>').text(message);
            var input = jQuery('<input type="text" class="watchtower-dialog-input">').val(defaultValue || '');
            body.append(input);
            var footer = jQuery('<div class="watchtower-dialog-footer"></div>');
            var cancelButton = jQuery('<button class="watchtower-dialog-button secondary">Cancel</button>');
            var okButton = jQuery('<button class="watchtower-dialog-button primary">OK</button>');

            footer.append(okButton).append(cancelButton);
            dialog.append(body).append(footer);
            overlay.append(dialog);

            okButton.on('click', function() {
                jQuery(document).off('keydown.watchtower-dialog');
                overlay.remove();
                resolve(input.val());
            });

            cancelButton.on('click', function() {
                jQuery(document).off('keydown.watchtower-dialog');
                overlay.remove();
                resolve(null);
            });

            overlay.on('click', function(e) {
                if (jQuery(e.target).hasClass('watchtower-dialog-overlay')) {
                    jQuery(document).off('keydown.watchtower-dialog');
                    overlay.remove();
                    resolve(null);
                }
            });

            jQuery(document).on('keydown.watchtower-dialog', function(e) {
                if (e.key === 'Escape') {
                    jQuery(document).off('keydown.watchtower-dialog');
                    overlay.remove();
                    resolve(null);
                } else if (e.key === 'Enter') {
                    jQuery(document).off('keydown.watchtower-dialog');
                    overlay.remove();
                    resolve(input.val());
                }
            });

            jQuery('body').append(overlay);
            input.focus().select();
        });
    }

    function toggleHealthDetails(event) {
        event.preventDefault();
        var meta = document.querySelector('.health-status-meta');
        var icon = event.currentTarget.querySelector('.dashicons');

        if (meta.style.display === 'none') {
            meta.style.display = 'block';
            icon.classList.remove('dashicons-arrow-down-alt2');
            icon.classList.add('dashicons-arrow-up-alt2');
        } else {
            meta.style.display = 'none';
            icon.classList.remove('dashicons-arrow-up-alt2');
            icon.classList.add('dashicons-arrow-down-alt2');
        }
    }

    function copyTokenToClipboard() {
        var tokenElement = document.getElementById('agent-token-value');
        var feedback = document.getElementById('copy-feedback');

        if (tokenElement) {
            var tokenValue = tokenElement.textContent || tokenElement.innerText;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(tokenValue).then(function() {
                    showCopyFeedback(feedback);
                }).catch(function(err) {
                    console.error('Failed to copy token: ', err);
                });
            } else {
                var textarea = document.createElement('textarea');
                textarea.value = tokenValue;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    showCopyFeedback(feedback);
                } catch (err) {
                    console.error('Failed to copy token: ', err);
                }
                document.body.removeChild(textarea);
            }
        }
    }

    function showCopyFeedback(feedback) {
        if (feedback) {
            feedback.style.display = 'inline';
            setTimeout(function() {
                feedback.style.display = 'none';
            }, 2000);
        }
    }

    jQuery(document).ready(function($) {
        $('.watchtower-update-agent-btn').on('click', function() {
            var button = $(this);
            var siteUrl = button.data('site');

            showConfirm('Are you sure you want to update the agent plugin on this site?').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; display: inline-block; transform-origin: center center; animation: rotation 2s infinite linear;"></span> Updating...');

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_update_agent',
                    site: siteUrl,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success) {
                        button.html('<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span> Update Successful!').css('color', '#00a32a');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showAlert('Update failed: ' + (response.data.message || 'Unknown error')).then(function() {
                            button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="margin-top: 3px;"></span> Update Remote Agent');
                        });
                    }
                }).fail(function() {
                    showAlert('Update request failed. Please try again.').then(function() {
                        button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="margin-top: 3px;"></span> Update Remote Agent');
                    });
                });
            });
        });

        $('.watchtower-toggle-debug-btn').on('click', function() {
            var button = $(this);
            var siteUrl = button.data('site');
            var debugEnabled = button.data('debug-enabled') === 1;
            var newState = !debugEnabled;

            showConfirm('Are you sure you want to ' + (newState ? 'enable' : 'disable') + ' debug mode on this site?').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; display: inline-block; transform-origin: center center; animation: rotation 2s infinite linear;"></span> ' + (newState ? 'Enabling' : 'Disabling') + '...');

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_toggle_debug',
                    site: siteUrl,
                    enabled: newState,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success) {
                        button.html('<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span> ' + (newState ? 'Debug Enabled!' : 'Debug Disabled!')).css('color', '#00a32a');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showAlert('Failed to toggle debug mode: ' + (response.data.message || 'Unknown error')).then(function() {
                            button.prop('disabled', false).html('<span class="dashicons dashicons-' + (debugEnabled ? 'no' : 'yes') + '" style="margin-top: 3px;"></span> ' + (debugEnabled ? 'Disable' : 'Enable') + ' Debug');
                        });
                    }
                }).fail(function() {
                    showAlert('Request failed. Please try again.').then(function() {
                        button.prop('disabled', false).html('<span class="dashicons dashicons-' + (debugEnabled ? 'no' : 'yes') + '" style="margin-top: 3px;"></span> ' + (debugEnabled ? 'Disable' : 'Enable') + ' Debug');
                    });
                });
            });
        });

        $('.watchtower-scan-btn').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var siteUrl = button.data('site');
            var originalHtml = button.html();

            button.html('<span class="dashicons dashicons-update" style="margin-top: 3px; display: inline-block; transform-origin: center center; animation: rotation 2s infinite linear;"></span> Scanning...').prop('disabled', true);

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_scan_agent',
                site: siteUrl,
                nonce: context.nonce
            }, function(response) {
                if (response.success) {
                    button.html('<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span> Done!');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    showAlert('Scan failed: ' + response.data.message).then(function() {
                        button.html(originalHtml).prop('disabled', false);
                    });
                }
            }).fail(function() {
                showAlert('Scan request failed. Please try again.').then(function() {
                    button.html(originalHtml).prop('disabled', false);
                });
            });
        });

        $('.watchtower-tab-btn').on('click', function() {
            var tab = $(this).data('tab');

            if (!tab) {
                return;
            }

            $('.watchtower-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('#mobile-tab-selector').val(tab);

            $('.watchtower-tab-content').hide();
            $('#tab-' + tab).show();

            window.location.hash = '#' + tab;

            $('.watchtower-overflow-menu').removeClass('show');

            if (tab === 'logs') {
                if (!$('#log-type-selector').data('loaded')) {
                    loadAvailableLogs();
                    setTimeout(function() {
                        loadLogs();
                    }, 200);
                } else {
                    loadLogs();
                }
            }

            if (tab === 'backups' && !$('#backups-table').data('loaded')) {
                loadBackups();
            }

            if (tab === 'activity') {
                loadActivityLogs();
            }

            if (tab === 'users') {
                loadUsers();
            }

            if (tab === 'files') {
                loadFiles('/');
            }
        });

        $('#mobile-tab-selector').on('change', function() {
            var tab = $(this).val();

            $('.watchtower-tab-btn').removeClass('active');
            $('.watchtower-tab-btn[data-tab="' + tab + '"]').addClass('active');

            $('.watchtower-tab-content').hide();
            $('#tab-' + tab).show();

            window.location.hash = '#' + tab;

            if (tab === 'activity') {
                loadActivityLogs();
            }

            if (tab === 'logs') {
                if (!$('#log-type-selector').data('loaded')) {
                    loadAvailableLogs();
                    setTimeout(function() {
                        loadLogs();
                    }, 200);
                } else {
                    loadLogs();
                }
            }

            if (tab === 'backups' && !$('#backups-table').data('loaded')) {
                loadBackups();
            }

            if (tab === 'users') {
                loadUsers();
            }

            if (tab === 'files') {
                loadFiles('/');
            }
        });

        function loadBackups(forceRefresh) {
            $('#backups-loading').show();
            $('#backups-container').hide();
            $('#backups-empty').hide();
            $('#backups-error').hide();

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_get_backups',
                site: context.siteUrl,
                nonce: context.nonce,
                force_refresh: forceRefresh || false
            }, function(response) {
                $('#backups-loading').hide();

                if (response.success && response.data.success) {
                    var backups = response.data.backups || [];

                    if (backups.length > 0) {
                        var tbody = $('#backups-tbody');
                        tbody.empty();

                        backups.forEach(function(backup) {
                            var componentsHtml = backup.components.map(function(comp) {
                                return '<span class="backup-component-badge">' + comp + '</span>';
                            }).join(' ');

                            var sizeText = backup.size ? formatBytes(backup.size) : '-';

                            var dateFormatted = backup.date.replace(' ', ' at ');

                            var row = $('<tr></tr>');
                            row.append('<td><strong>' + dateFormatted + '</strong></td>');
                            row.append('<td>' + sizeText + '</td>');
                            row.append('<td>' + componentsHtml + '</td>');
                            row.append('<td style="text-align: right;">' +
                                '<button class="button button-small restore-backup-btn" data-backup-id="' + backup.id + '" data-backup-date="' + backup.date + '">Restore</button> ' +
                                '<button class="button button-small delete-backup-btn" data-backup-id="' + backup.id + '" data-backup-date="' + backup.date + '" style="color: #d63638;">Delete</button>' +
                                '</td>');
                            tbody.append(row);
                        });

                        $('#backups-container').show();
                        $('#backups-table').data('loaded', true);
                    } else {
                        $('#backups-empty').show();
                    }
                } else {
                    $('#backups-error').show();
                    $('#backups-error-message').text(response.data && response.data.error ? response.data.error : 'Failed to load backups');
                }
            }).fail(function() {
                $('#backups-loading').hide();
                $('#backups-error').show();
                $('#backups-error-message').text('Network error while loading backups');
            });
        }

        $('#create-backup-btn').on('click', function() {
            var button = $(this);

            showConfirm('Create a new backup? This may take several minutes.').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                $('#backup-progress-modal').css('display', 'flex');
                $('#backup-progress-message').text('Starting backup...');
                $('#backup-progress-bar').css('width', '0%');
                $('#backup-progress-percent').text('0%');
                $('#backup-progress-close').hide();

                button.prop('disabled', true);

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_create_backup',
                    site: context.siteUrl,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success && response.data && response.data.id) {
                        pollBackupProgress(response.data.id);
                    } else {
                        $('#backup-progress-message').html('<span style="color: #d63638;">Failed: ' + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</span>');
                        $('#backup-progress-close').show();
                        button.prop('disabled', false);
                    }
                }).fail(function() {
                    $('#backup-progress-message').html('<span style="color: #d63638;">Network error while starting backup</span>');
                    $('#backup-progress-close').show();
                    button.prop('disabled', false);
                });
            });
        });

        $('#refresh-backups-btn').on('click', function() {
            $('#backups-table').data('loaded', false);
            loadBackups();
        });

        $(document).on('click', '.restore-backup-btn', function() {
            var button = $(this);
            var backupId = button.data('backup-id');
            var backupDate = button.data('backup-date');

            showConfirm('Restore backup from ' + backupDate + '?\n\nWARNING: This will overwrite your current site data. The site may be temporarily unavailable during restore.').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                $('#restore-progress-modal').css('display', 'flex');
                $('#restore-progress-message').text('Starting restore...');
                $('#restore-progress-bar').css('width', '0%');
                $('#restore-progress-percent').text('0%');
                $('#restore-progress-close').hide();

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_restore_backup',
                    site: context.siteUrl,
                    backup_id: backupId,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success) {
                        setTimeout(function() {
                            pollRestoreProgress();
                        }, 3000);
                    } else {
                        $('#restore-progress-message').html('<span style="color: #d63638;">Failed: ' + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</span>');
                        $('#restore-progress-close').show();
                    }
                }).fail(function() {
                    $('#restore-progress-message').html('<span style="color: #d63638;">Network error while starting restore</span>');
                    $('#restore-progress-close').show();
                });
            });
        });

        var restoreProgressInterval;
        function pollRestoreProgress() {
            restoreProgressInterval = setInterval(function() {
                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_get_restore_status',
                    site: context.siteUrl,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success && response.data.success) {
                        var status = response.data.status;
                        var percent = response.data.percent_complete;
                        var message = response.data.message;

                        $('#restore-progress-bar').css('width', percent + '%');
                        $('#restore-progress-percent').text(percent + '%');
                        $('#restore-progress-message').text(message);

                        if (status === 'complete') {
                            clearInterval(restoreProgressInterval);
                            $('#restore-progress-message').html('<span style="color: #00a32a;">Restore completed successfully!</span>');
                            $('#restore-progress-close').show();
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else if (status === 'error') {
                            clearInterval(restoreProgressInterval);
                            $('#restore-progress-message').html('<span style="color: #d63638;">Restore failed: ' + message + '</span>');
                            $('#restore-progress-close').show();
                        }
                    }
                });
            }, 2000); // Poll every 2 seconds
        }

        $('#restore-progress-close').on('click', function() {
            if (restoreProgressInterval) {
                clearInterval(restoreProgressInterval);
            }
            $('#restore-progress-modal').hide();
        });

        var backupProgressInterval;
        function pollBackupProgress(backupId) {
            backupProgressInterval = setInterval(function() {
                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_get_backup_status',
                    site: context.siteUrl,
                    backup_id: backupId,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success && response.data.success) {
                        var status = response.data.status;
                        var percent = response.data.percent_complete;
                        var message = response.data.message;

                        $('#backup-progress-bar').css('width', percent + '%');
                        $('#backup-progress-percent').text(percent + '%');
                        $('#backup-progress-message').text(message);

                        if (status === 'complete') {
                            clearInterval(backupProgressInterval);
                            $('#backup-progress-message').html('<span style="color: #00a32a;">Backup completed successfully!</span>');
                            $('#backup-progress-close').show();
                            $('#create-backup-btn').prop('disabled', false);
                            $('#backups-table').data('loaded', false);
                            loadBackups(true);
                        } else if (status === 'error') {
                            clearInterval(backupProgressInterval);
                            $('#backup-progress-message').html('<span style="color: #d63638;">Backup failed: ' + message + '</span>');
                            $('#backup-progress-close').show();
                            $('#create-backup-btn').prop('disabled', false);
                        }
                    }
                });
            }, 2000);
        }

        $('#backup-progress-close').on('click', function() {
            if (backupProgressInterval) {
                clearInterval(backupProgressInterval);
            }
            $('#backup-progress-modal').hide();
        });

        $(document).on('click', '.delete-backup-btn', function() {
            var button = $(this);
            var backupId = button.data('backup-id');
            var backupDate = button.data('backup-date');

            showConfirm('Delete backup from ' + backupDate + '? This cannot be undone.').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; display: inline-block; transform-origin: center center; animation: rotation 2s infinite linear;"></span> Deleting...');

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_delete_backup',
                    site: context.siteUrl,
                    backup_id: backupId,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success && response.data && response.data.success) {
                        $('#backups-table').data('loaded', false);
                        loadBackups(true);
                    } else {
                        var errorMsg = 'Failed to delete backup';
                        if (response.data && response.data.message) {
                            errorMsg += ': ' + response.data.message;
                        } else if (response.data && response.data.error) {
                            errorMsg += ': ' + response.data.error;
                        }
                        showAlert(errorMsg).then(function() {
                            button.prop('disabled', false).text('Delete').css('color', '#d63638');
                        });
                    }
                }).fail(function() {
                    showAlert('Network error while deleting backup').then(function() {
                        button.prop('disabled', false).text('Delete').css('color', '#d63638');
                    });
                });
            });
        });

        function loadAvailableLogs() {
            $.post(context.ajaxurl, {
                action: 'watchtower_manager_get_available_logs',
                site: context.siteUrl,
                nonce: context.nonce
            }, function(response) {
                if (response.success && response.data.logs) {
                    var selector = $('#log-type-selector');
                    selector.empty();

                    var hasReadableLogs = false;
                    response.data.logs.forEach(function(log) {
                        if (log.readable) {
                            selector.append('<option value="' + log.type + '">' +
                                log.type.charAt(0).toUpperCase() + log.type.slice(1) +
                                ' (' + formatBytes(log.size) + ')</option>');
                            hasReadableLogs = true;
                        }
                    });

                    if (!hasReadableLogs) {
                        selector.append('<option value="">No readable logs found</option>');
                        $('#log-viewer').html('<span style="color: #888;">No readable log files found on this site.</span>');
                    } else {
                        selector.data('loaded', true);
                        loadLogs();
                    }
                }
            });
        }

        function formatLogLine(line) {
            line = $('<div>').text(line).html();

            var timestampMatch = line.match(/^(\[[^\]]+\])/);
            var timestamp = '';
            var message = line;

            if (timestampMatch) {
                timestamp = '<span style="color: #858585;">' + timestampMatch[1] + '</span> ';
                message = line.substring(timestampMatch[1].length).trim();
            }

            if (message.match(/PHP Fatal [Ee]rror/i)) {
                message = '<span style="color: #ff5555; font-weight: bold;">' + message + '</span>';
            } else if (message.match(/PHP Parse [Ee]rror/i)) {
                message = '<span style="color: #ff5555; font-weight: bold;">' + message + '</span>';
            } else if (message.match(/PHP Warning/i)) {
                message = '<span style="color: #ffb86c;">' + message + '</span>';
            } else if (message.match(/PHP Notice/i)) {
                message = '<span style="color: #8be9fd;">' + message + '</span>';
            } else if (message.match(/PHP Deprecated/i)) {
                message = '<span style="color: #bd93f9;">' + message + '</span>';
            } else if (message.match(/\b(ERROR|Error|error)\b/)) {
                message = '<span style="color: #ff6b6b;">' + message + '</span>';
            } else if (message.match(/\b(CRITICAL|Critical)\b/)) {
                message = '<span style="color: #ff5555; font-weight: bold;">' + message + '</span>';
            } else if (message.match(/\b(WARNING|Warning)\b/)) {
                message = '<span style="color: #f1fa8c;">' + message + '</span>';
            } else if (message.match(/\b(INFO|Info)\b/)) {
                message = '<span style="color: #50fa7b;">' + message + '</span>';
            } else if (message.match(/\b(DEBUG|Debug)\b/)) {
                message = '<span style="color: #6272a4;">' + message + '</span>';
            }

            return timestamp + message;
        }

        function loadLogs() {
            var logType = $('#log-type-selector').val();
            var lines = $('#log-lines-count').val();

            if (!logType) return;

            $('#log-viewer').html('<span style="color: #888;">Loading logs...</span>');
            $('#refresh-logs-btn').prop('disabled', true);

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_get_logs',
                site: context.siteUrl,
                log_type: logType,
                lines: lines,
                nonce: context.nonce
            }, function(response) {
                $('#refresh-logs-btn').prop('disabled', false);

                if (response.success && response.data.success) {
                    if (response.data.logs && response.data.logs.length > 0) {
                        var formattedLogs = response.data.logs.map(function(line) {
                            return formatLogLine(line);
                        }).join('\n');
                        $('#log-viewer').html(formattedLogs);
                        $('#log-viewer').scrollTop($('#log-viewer')[0].scrollHeight);
                    } else {
                        $('#log-viewer').html('<span style="color: #888;">No log entries found.</span>');
                    }
                } else {
                    $('#log-viewer').html('<span style="color: #ff6b6b;">Error: ' +
                        (response.data.error || 'Failed to load logs') + '</span>');
                }
            }).fail(function() {
                $('#refresh-logs-btn').prop('disabled', false);
                $('#log-viewer').html('<span style="color: #ff6b6b;">Failed to load logs. Please try again.</span>');
            });
        }

        function formatBytes(bytes) {
            if (bytes === 0 || bytes === null) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        $('#log-type-selector').on('change', loadLogs);
        $('#log-lines-count').on('change', loadLogs);
        $('#refresh-logs-btn').on('click', loadLogs);

        var activitySelectedDate = null;
        var activitySiteUrl = context.siteUrl;
        var activityAllEntries = [];  // All entries for the selected date
        var activitySelectedActions = [];  // Selected action filters
        var activitySelectedActors = [];   // Selected actor filters

        $('#activity-date-picker').datepicker({
            dateFormat: 'yy-mm-dd',
            maxDate: 0,
            onSelect: function(dateText) {
                activitySelectedDate = dateText;
                activitySelectedActions = [];
                activitySelectedActors = [];
                loadActivityLogs();
            }
        });

        var today = new Date();
        var todayStr = today.getUTCFullYear() + '-' +
            String(today.getUTCMonth() + 1).padStart(2, '0') + '-' +
            String(today.getUTCDate()).padStart(2, '0');
        $('#activity-date-picker').val(todayStr);
        activitySelectedDate = todayStr;

        function loadActivityLogs() {
            if (!activitySelectedDate) {
                return;
            }

            var dateParts = activitySelectedDate.split('-');
            var year = parseInt(dateParts[0]);
            var month = parseInt(dateParts[1]) - 1; // JavaScript months are 0-indexed
            var day = parseInt(dateParts[2]);

            var startOfDay = Date.UTC(year, month, day, 0, 0, 0) / 1000;
            var endOfDay = Date.UTC(year, month, day, 23, 59, 59) / 1000;

            var hasContent = $('#activity-log-viewer').find('table').length > 0;
            if (hasContent) {
                if ($('#activity-loading-overlay').length === 0) {
                    $('#activity-log-viewer').prepend('<div id="activity-loading-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 100; display: flex; align-items: center; justify-content: center;"><div class="watchtower-spinner"></div></div>');
                }
            } else {
                $('#activity-log-viewer').html('<div style="text-align: center; padding: 50px 0; color: #888;"><div class="watchtower-spinner" style="margin: 0 auto 15px;"></div><p>Loading activity logs...</p></div>');
            }

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_get_activity_logs',
                site: activitySiteUrl,
                from: startOfDay,
                to: endOfDay,
                nonce: context.nonce
            }, function(response) {
                $('#activity-loading-overlay').remove();

                if (response.success && response.data) {
                    var data = response.data;
                    if (data.success && data.entries && data.entries.length > 0) {
                        activityAllEntries = data.entries;

                        populateActionFilter();
                        populateActorFilter();

                        applyFiltersAndDisplay();
                    } else {
                        activityAllEntries = [];
                        $('#activity-log-viewer').html('<div style="text-align: center; padding: 50px 0; color: #888;"><span class="dashicons dashicons-info" style="font-size: 32px;"></span><p>No activity logs found for ' + activitySelectedDate + '</p></div>');
                        $('#activity-count-visible').text('0');
                        $('#activity-count-total').text('0');
                    }
                } else {
                    activityAllEntries = [];
                    var errorMsg = 'Failed to load activity logs';
                    if (response.data && response.data.message) {
                        errorMsg += ': ' + response.data.message;
                    }
                    $('#activity-log-viewer').html('<div style="text-align: center; padding: 50px 0; color: #d63638;"><p>' + errorMsg + '</p></div>');
                    $('#activity-count-visible').text('0');
                    $('#activity-count-total').text('0');
                }
            }).fail(function() {
                $('#activity-loading-overlay').remove();

                activityAllEntries = [];
                $('#activity-log-viewer').html('<div style="text-align: center; padding: 50px 0; color: #d63638;"><p>Failed to retrieve activity logs</p></div>');
                $('#activity-count-visible').text('0');
                $('#activity-count-total').text('0');
            });
        }

        function displayActivityLogs(entries) {
            var tableHtml = '<table class="wp-list-table widefat fixed striped activity-table">';
            tableHtml += '<thead><tr>';
            tableHtml += '<th style="width: 180px;">Time</th>';
            tableHtml += '<th>Action</th>';
            tableHtml += '<th>Actor</th>';
            tableHtml += '<th>IP Address</th>';
            tableHtml += '<th>Details</th>';
            tableHtml += '</tr></thead><tbody>';

            entries.forEach(function(entry) {
                tableHtml += '<tr>';
                tableHtml += '<td style="white-space: nowrap;">' + escapeHtml(entry.timestamp) + '</td>';
                tableHtml += '<td><span style="background: #e3f2fd; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 500;">' + escapeHtml(entry.action) + '</span></td>';
                tableHtml += '<td>' + escapeHtml(entry.actor_name) + '</td>';
                tableHtml += '<td>' + escapeHtml(entry.ip_address) + '</td>';
                tableHtml += '<td>' + formatDetails(entry.details) + '</td>';
                tableHtml += '</tr>';
            });

            tableHtml += '</tbody></table>';

            var mobileHtml = '<div class="mobile-activity-grid">';
            entries.forEach(function(entry) {
                mobileHtml += '<div class="mobile-activity-card">';
                mobileHtml += '<div class="mobile-activity-header">';
                mobileHtml += '<div class="mobile-activity-left">';
                mobileHtml += '<div class="mobile-activity-actor">' + escapeHtml(entry.actor_name) + ' • ' + escapeHtml(entry.ip_address) + '</div>';
                mobileHtml += '<div class="mobile-activity-time">' + escapeHtml(entry.timestamp) + '</div>';
                mobileHtml += '</div>';
                mobileHtml += '<div class="mobile-activity-action">' + escapeHtml(entry.action) + '</div>';
                mobileHtml += '</div>';
                if (entry.details && Object.keys(entry.details).length > 0) {
                    mobileHtml += '<div class="mobile-activity-info">';
                    mobileHtml += '<div class="mobile-activity-meta">' + formatDetails(entry.details) + '</div>';
                    mobileHtml += '</div>';
                }
                mobileHtml += '</div>';
            });
            mobileHtml += '</div>';

            $('#activity-log-viewer').html(tableHtml + mobileHtml);
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function formatDetails(details) {
            if (!details || Object.keys(details).length === 0) {
                return '<span style="color: #999;">—</span>';
            }

            var html = '';
            var keys = Object.keys(details);

            keys.forEach(function(key, index) {
                var value = details[key];
                var formattedValue = '';

                if (value === null || value === undefined) {
                    formattedValue = '<span style="color: #999;">null</span>';
                } else if (typeof value === 'object' && !Array.isArray(value)) {
                    var subKeys = Object.keys(value);
                    if (subKeys.length <= 3) {
                        var subParts = [];
                        subKeys.forEach(function(subKey) {
                            subParts.push(subKey + ': ' + escapeHtml(String(value[subKey])));
                        });
                        formattedValue = '{' + subParts.join(', ') + '}';
                    } else {
                        formattedValue = '<div style="margin-left: 12px;">';
                        subKeys.forEach(function(subKey) {
                            formattedValue += '• ' + escapeHtml(subKey) + ': ' + escapeHtml(String(value[subKey])) + '<br>';
                        });
                        formattedValue += '</div>';
                    }
                } else if (Array.isArray(value)) {
                    formattedValue = '[' + value.map(function(v) { return escapeHtml(String(v)); }).join(', ') + ']';
                } else {
                    formattedValue = escapeHtml(String(value));
                }

                if (index > 0) {
                    html += '<br>';
                }
                html += '<strong>' + escapeHtml(key) + ':</strong> ' + formattedValue;
            });

            return html;
        }

        function populateActionFilter() {
            var actions = {};
            activityAllEntries.forEach(function(entry) {
                actions[entry.action] = (actions[entry.action] || 0) + 1;
            });

            var html = '<div style="padding: 8px;">';
            Object.keys(actions).sort().forEach(function(action) {
                var checked = activitySelectedActions.length === 0 || activitySelectedActions.indexOf(action) !== -1;
                html += '<label style="display: block; padding: 4px 8px; cursor: pointer; user-select: none;">';
                html += '<input type="checkbox" class="action-filter-checkbox" value="' + escapeHtml(action) + '" ' + (checked ? 'checked' : '') + '> ';
                html += escapeHtml(action) + ' <span style="color: #999;">(' + actions[action] + ')</span>';
                html += '</label>';
            });
            html += '</div>';
            $('#activity-action-dropdown').html(html);
        }

        function populateActorFilter() {
            var actors = {};
            activityAllEntries.forEach(function(entry) {
                actors[entry.actor_name] = (actors[entry.actor_name] || 0) + 1;
            });

            var html = '<div style="padding: 8px;">';
            Object.keys(actors).sort().forEach(function(actor) {
                var checked = activitySelectedActors.length === 0 || activitySelectedActors.indexOf(actor) !== -1;
                html += '<label style="display: block; padding: 4px 8px; cursor: pointer; user-select: none;">';
                html += '<input type="checkbox" class="actor-filter-checkbox" value="' + escapeHtml(actor) + '" ' + (checked ? 'checked' : '') + '> ';
                html += escapeHtml(actor) + ' <span style="color: #999;">(' + actors[actor] + ')</span>';
                html += '</label>';
            });
            html += '</div>';
            $('#activity-actor-dropdown').html(html);
        }

        function applyFiltersAndDisplay() {
            var filteredEntries = activityAllEntries.filter(function(entry) {
                var actionMatch = activitySelectedActions.length === 0 || activitySelectedActions.indexOf(entry.action) !== -1;
                var actorMatch = activitySelectedActors.length === 0 || activitySelectedActors.indexOf(entry.actor_name) !== -1;
                return actionMatch && actorMatch;
            });

            displayActivityLogs(filteredEntries);
            $('#activity-count-visible').text(filteredEntries.length);
            $('#activity-count-total').text(activityAllEntries.length);

            if (activitySelectedActions.length === 0) {
                $('#activity-action-filter').html('All Actions <span class="dashicons dashicons-arrow-down-alt2" style="float: right; margin-top: 5px;"></span>');
            } else {
                $('#activity-action-filter').html(activitySelectedActions.length + ' selected <span class="dashicons dashicons-arrow-down-alt2" style="float: right; margin-top: 5px;"></span>');
            }

            if (activitySelectedActors.length === 0) {
                $('#activity-actor-filter').html('All Actors <span class="dashicons dashicons-arrow-down-alt2" style="float: right; margin-top: 5px;"></span>');
            } else {
                $('#activity-actor-filter').html(activitySelectedActors.length + ' selected <span class="dashicons dashicons-arrow-down-alt2" style="float: right; margin-top: 5px;"></span>');
            }
        }

        $('#activity-action-filter').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#activity-actor-dropdown').hide();
            $('#activity-action-dropdown').toggle();
        });

        $('#activity-actor-filter').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#activity-action-dropdown').hide();
            $('#activity-actor-dropdown').toggle();
        });

        $(document).on('click', function() {
            $('#activity-action-dropdown').hide();
            $('#activity-actor-dropdown').hide();
        });

        $(document).on('change', '.action-filter-checkbox', function(e) {
            e.stopPropagation();
            activitySelectedActions = [];
            $('.action-filter-checkbox:checked').each(function() {
                activitySelectedActions.push($(this).val());
            });
            applyFiltersAndDisplay();
        });

        $(document).on('change', '.actor-filter-checkbox', function(e) {
            e.stopPropagation();
            activitySelectedActors = [];
            $('.actor-filter-checkbox:checked').each(function() {
                activitySelectedActors.push($(this).val());
            });
            applyFiltersAndDisplay();
        });

        $('#activity-action-dropdown, #activity-actor-dropdown').on('click', function(e) {
            e.stopPropagation();
        });

        $('#activity-refresh-btn').on('click', function(e) {
            e.preventDefault();
            loadActivityLogs();
        });

        function loadUsers() {
            $('#users-loading').show();
            $('#users-container').hide();
            $('#users-empty').hide();
            $('#users-error').hide();

            $.ajax({
                url: context.ajaxurl,
                type: 'POST',
                data: {
                    action: 'watchtower_manager_get_users',
                    site: context.siteUrl,
                    nonce: context.nonce
                },
                timeout: 30000,
                success: function(response) {
                    $('#users-loading').hide();

                    if (response.success && response.data.users && response.data.users.length > 0) {
                        displayUsers(response.data.users);
                        $('#users-container').show();
                    } else if (response.success && response.data.users && response.data.users.length === 0) {
                        $('#users-empty').show();
                    } else {
                        $('#users-error-message').text(response.data && response.data.message ? response.data.message : 'Failed to load users');
                        $('#users-error').show();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $('#users-loading').hide();
                    $('#users-error-message').text('Network error: ' + textStatus);
                    $('#users-error').show();
                }
            });
        }

        function displayUsers(users) {
            var tbody = $('#users-table-body');
            tbody.empty();

            var mobileGrid = $('#mobile-users-grid');
            mobileGrid.empty();

            $('#users-count').text(users.length);

            users.forEach(function(user) {
                var currentRole = user.roles.indexOf('administrator') >= 0 ? 'Administrator' :
                                  user.roles.indexOf('editor') >= 0 ? 'Editor' :
                                  user.roles.indexOf('author') >= 0 ? 'Author' :
                                  user.roles.indexOf('contributor') >= 0 ? 'Contributor' : 'Subscriber';

                var row = $('<tr data-user-id="' + user.id + '" data-email="' + escapeHtml(user.email) + '" data-first-name="' + escapeHtml(user.first_name || '') + '" data-last-name="' + escapeHtml(user.last_name || '') + '" data-role="' + user.roles[0] + '" data-username="' + escapeHtml(user.username) + '">' +
                    '<td data-label="Username"><strong>' + escapeHtml(user.username) + '</strong></td>' +
                    '<td data-label="Email" class="user-email-cell">' + escapeHtml(user.email) + '</td>' +
                    '<td data-label="First Name" class="user-first-name-cell">' + escapeHtml(user.first_name || '') + '</td>' +
                    '<td data-label="Last Name" class="user-last-name-cell">' + escapeHtml(user.last_name || '') + '</td>' +
                    '<td data-label="Role" class="user-role-cell">' + currentRole + '</td>' +
                    '<td class="user-actions-cell">' +
                    '<div class="button-group">' +
                    '<button class="button button-primary edit-user-quick-btn" data-user-id="' + user.id + '">Edit</button>' +
                    '<button class="button button-primary edit-user-dropdown-btn" data-user-id="' + user.id + '"><span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
                    '<div class="edit-dropdown-menu" data-user-id="' + user.id + '">' +
                    '<a href="#" class="edit-dropdown-item quick-edit-item" data-user-id="' + user.id + '">Quick Edit</a>' +
                    '<a href="#" class="edit-dropdown-item full-edit-item" data-user-id="' + user.id + '">Full Edit</a>' +
                    '<a href="#" class="edit-dropdown-item reset-password-item" data-user-id="' + user.id + '" data-user-email="' + escapeHtml(user.email) + '">Reset Password</a>' +
                    '</div>' +
                    '</div>' +
                    '<button class="button delete-user-btn" data-user-id="' + user.id + '" data-username="' + escapeHtml(user.username) + '">Delete</button>' +
                    '</td>' +
                    '</tr>');
                tbody.append(row);

                var fullName = '';
                if (user.first_name || user.last_name) {
                    fullName = escapeHtml((user.first_name || '') + ' ' + (user.last_name || '')).trim();
                }

                var card = $('<div class="mobile-user-card" data-user-id="' + user.id + '" data-email="' + escapeHtml(user.email) + '" data-first-name="' + escapeHtml(user.first_name || '') + '" data-last-name="' + escapeHtml(user.last_name || '') + '" data-role="' + user.roles[0] + '" data-username="' + escapeHtml(user.username) + '">' +
                    '<div class="mobile-user-header">' +
                    '<div class="mobile-user-title">' +
                    '<strong>' + escapeHtml(user.username) + '</strong>' +
                    (fullName ? '<div class="mobile-user-meta">' + fullName + '</div>' : '') +
                    '</div>' +
                    '<div class="mobile-user-role">' + currentRole + '</div>' +
                    '</div>' +
                    '<div class="mobile-user-info">' +
                    '<div class="mobile-user-meta">' + escapeHtml(user.email) + '</div>' +
                    '</div>' +
                    '<div class="mobile-user-actions">' +
                    '<button class="button button-primary mobile-edit-user-btn" data-user-id="' + user.id + '">Edit</button>' +
                    '<button class="button delete-user-btn" data-user-id="' + user.id + '" data-username="' + escapeHtml(user.username) + '">Delete</button>' +
                    '</div>' +
                    '</div>');
                mobileGrid.append(card);
            });

            $('.edit-dropdown-item').on('mouseenter', function() {
                $(this).css('background', '#f6f7f7');
            }).on('mouseleave', function() {
                $(this).css('background', '#fff');
            });
        }

        $('#create-user-btn').on('click', function() {
            $('#user-modal-title').text('Create New User');
            $('#new-username').val('').prop('disabled', false);
            $('#new-email').val('');
            $('#new-password').val('');
            $('#new-password-label').text('Password *');
            $('#new-first-name').val('');
            $('#new-last-name').val('');
            $('#new-role').val('administrator');
            $('#confirm-create-user-btn').text('Create User').data('mode', 'create').removeData('user-id');
            $('#create-user-modal').css('display', 'flex');
        });

        $('#cancel-create-user-btn').on('click', function() {
            $('#create-user-modal').hide();
        });

        $('#confirm-create-user-btn').on('click', function() {
            var mode = $(this).data('mode') || 'create';
            var userId = $(this).data('user-id');
            var username = $('#new-username').val().trim();
            var email = $('#new-email').val().trim();
            var password = $('#new-password').val();
            var firstName = $('#new-first-name').val().trim();
            var lastName = $('#new-last-name').val().trim();
            var role = $('#new-role').val();

            if (mode === 'create') {
                if (!username || !email || !password) {
                    showAlert('Please fill in all required fields (Username, Email, Password)');
                    return;
                }

                $(this).prop('disabled', true).text('Creating...');

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_create_user',
                    site: context.siteUrl,
                    nonce: context.nonce,
                    username: username,
                    email: email,
                    password: password,
                    first_name: firstName,
                    last_name: lastName,
                    role: role
                }, function(response) {
                    if (response.success) {
                        $('#create-user-modal').hide();
                        loadUsers();
                    } else {
                        showAlert('Failed to create user: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                    $('#confirm-create-user-btn').prop('disabled', false).text('Create User');
                }).fail(function() {
                    showAlert('Network error occurred');
                    $('#confirm-create-user-btn').prop('disabled', false).text('Create User');
                });
            } else {
                if (!email) {
                    showAlert('Email cannot be empty');
                    return;
                }

                $(this).prop('disabled', true).text('Updating...');

                var updateData = {
                    action: 'watchtower_manager_update_user',
                    site: context.siteUrl,
                    nonce: context.nonce,
                    user_id: userId,
                    email: email,
                    first_name: firstName,
                    last_name: lastName,
                    role: role
                };

                if (password) {
                    updateData.password = password;
                }

                $.post(context.ajaxurl, updateData, function(response) {
                    if (response.success) {
                        $('#create-user-modal').hide();
                        showAlert('User updated successfully').then(function() {
                            loadUsers();
                        });
                    } else {
                        showAlert('Failed to update user: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                    $('#confirm-create-user-btn').prop('disabled', false).text('Update');
                }).fail(function() {
                    showAlert('Network error occurred');
                    $('#confirm-create-user-btn').prop('disabled', false).text('Update');
                });
            }
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.edit-user-dropdown-btn, .edit-dropdown-menu').length) {
                $('.edit-dropdown-menu').hide();
            }
        });

        $(document).on('click', '.edit-user-dropdown-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var userId = $(this).data('user-id');
            // Hide all dropdown menus first
            $('#users-table-body .edit-dropdown-menu').hide();
            // Show only this specific user's menu
            $(this).siblings('.edit-dropdown-menu[data-user-id="' + userId + '"]').show();
        });

        $(document).on('click', '.edit-user-quick-btn, .quick-edit-item', function(e) {
            e.preventDefault();
            $('.edit-dropdown-menu').hide();

            var userId = $(this).data('user-id');
            var row = $('#users-table-body tr[data-user-id="' + userId + '"]').first();

            if (row.length === 0) {
                return;
            }

            if (row.hasClass('editing')) {
                return;
            }

            // Cancel any other rows in edit mode first
            if ($('#users-table-body tr.editing').length > 0) {
                loadUsers();
                return;
            }

            var email = row.attr('data-email');
            var firstName = row.attr('data-first-name');
            var lastName = row.attr('data-last-name');
            var role = row.attr('data-role');

            var roleOptions = '<select class="user-role-edit" style="width: 100%; padding: 4px;">';
            roleOptions += '<option value="administrator"' + (role === 'administrator' ? ' selected' : '') + '>Administrator</option>';
            roleOptions += '<option value="editor"' + (role === 'editor' ? ' selected' : '') + '>Editor</option>';
            roleOptions += '<option value="author"' + (role === 'author' ? ' selected' : '') + '>Author</option>';
            roleOptions += '<option value="contributor"' + (role === 'contributor' ? ' selected' : '') + '>Contributor</option>';
            roleOptions += '<option value="subscriber"' + (role === 'subscriber' ? ' selected' : '') + '>Subscriber</option>';
            roleOptions += '</select>';

            row.addClass('editing');
            row.find('.user-email-cell').html('<input type="email" class="user-email-edit" value="' + escapeHtml(email) + '" style="width: 100%; padding: 4px;">');
            row.find('.user-first-name-cell').html('<input type="text" class="user-first-name-edit" value="' + escapeHtml(firstName) + '" style="width: 100%; padding: 4px;">');
            row.find('.user-last-name-cell').html('<input type="text" class="user-last-name-edit" value="' + escapeHtml(lastName) + '" style="width: 100%; padding: 4px;">');
            row.find('.user-role-cell').html(roleOptions);
            row.find('.user-actions-cell').html(
                '<button class="button button-primary save-user-btn" data-user-id="' + userId + '">Save</button> ' +
                '<button class="button cancel-edit-user-btn" data-user-id="' + userId + '">Cancel</button>'
            );
        });

        $(document).on('click', '.full-edit-item', function(e) {
            e.preventDefault();
            var userId = $(this).data('user-id');
            var row = $('tr[data-user-id="' + userId + '"]');

            var email = row.data('email');
            var firstName = row.data('first-name');
            var lastName = row.data('last-name');
            var role = row.data('role');
            var username = row.data('username');

            $('#user-modal-title').text('Edit User');
            $('#new-username').val(username).prop('disabled', true);
            $('#new-email').val(email);
            $('#new-password').val('');
            $('#new-password-label').text('New Password (leave blank to keep current)');
            $('#new-first-name').val(firstName);
            $('#new-last-name').val(lastName);
            $('#new-role').val(role);
            $('#confirm-create-user-btn').text('Update').data('user-id', userId).data('mode', 'edit');
            $('#create-user-modal').css('display', 'flex');

            $('.edit-dropdown-menu').hide();
        });

        $(document).on('click', '.cancel-edit-user-btn', function() {
            loadUsers();
        });

        $(document).on('click', '.save-user-btn', function() {
            var userId = $(this).data('user-id');
            var row = $('#users-table-body tr[data-user-id="' + userId + '"]').first();

            if (row.length === 0) {
                showAlert('Error: Could not find user row');
                return;
            }

            var email = row.find('.user-email-edit').val().trim();
            var firstName = row.find('.user-first-name-edit').val().trim();
            var lastName = row.find('.user-last-name-edit').val().trim();
            var role = row.find('.user-role-edit').val();

            if (!email) {
                showAlert('Email cannot be empty');
                return;
            }

            $(this).prop('disabled', true).text('Saving...');

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_update_user',
                site: context.siteUrl,
                nonce: context.nonce,
                user_id: userId,
                email: email,
                first_name: firstName,
                last_name: lastName,
                role: role
            }, function(response) {
                if (response.success) {
                    showAlert('User updated successfully').then(function() {
                        loadUsers();
                    });
                } else {
                    showAlert('Failed to update user: ' + (response.data && response.data.message ? response.data.message : 'Unknown error')).then(function() {
                        loadUsers();
                    });
                }
            }).fail(function() {
                showAlert('Network error occurred').then(function() {
                    loadUsers();
                });
            });
        });

        $(document).on('click', '.reset-password-item', function(e) {
            e.preventDefault();
            var userId = $(this).data('user-id');
            var userEmail = $(this).data('user-email');

            $('.edit-dropdown-menu').hide();

            showConfirm('Send password reset email to ' + userEmail + '?').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_reset_password',
                    site: context.siteUrl,
                    nonce: context.nonce,
                    user_id: userId
                }, function(response) {
                    if (response.success) {
                        showAlert('Password reset email sent successfully').then(function() {
                            loadUsers();
                        });
                    } else {
                        showAlert('Failed to send password reset: ' + (response.data && response.data.message ? response.data.message : 'Unknown error')).then(function() {
                            loadUsers();
                        });
                    }
                }).fail(function() {
                    showAlert('Network error occurred').then(function() {
                        loadUsers();
                    });
                });
            });
        });

        $(document).on('click', '.mobile-edit-user-btn', function(e) {
            e.preventDefault();
            var userId = $(this).data('user-id');
            var card = $('.mobile-user-card[data-user-id="' + userId + '"]');

            var email = card.data('email');
            var firstName = card.data('first-name');
            var lastName = card.data('last-name');
            var role = card.data('role');
            var username = card.data('username');

            $('#user-modal-title').text('Edit User');
            $('#new-username').val(username).prop('disabled', true);
            $('#new-email').val(email);
            $('#new-password').val('');
            $('#new-password-label').text('New Password (leave blank to keep current)');
            $('#new-first-name').val(firstName);
            $('#new-last-name').val(lastName);
            $('#new-role').val(role);
            $('#confirm-create-user-btn').text('Update').data('user-id', userId).data('mode', 'edit');
            $('#create-user-modal').css('display', 'flex');
        });

        $(document).on('click', '.delete-user-btn', function() {
            var userId = $(this).data('user-id');
            var username = $(this).data('username');

            showConfirm('Are you sure you want to delete user "' + username + '"? This action cannot be undone.').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                $(this).prop('disabled', true).text('Deleting...');

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_delete_user',
                    site: context.siteUrl,
                    nonce: context.nonce,
                    user_id: userId
                }, function(response) {
                    if (response.success) {
                        loadUsers();
                    } else {
                        showAlert('Failed to delete user: ' + (response.data && response.data.message ? response.data.message : 'Unknown error')).then(function() {
                            loadUsers();
                        });
                    }
                }).fail(function() {
                    showAlert('Network error occurred').then(function() {
                        loadUsers();
                    });
                });
            });
        });

        if (window.location.hash) {
            var hash = window.location.hash.substring(1);
            var validTabs = ['overview', 'plugins', 'users', 'activity', 'logs', 'files', 'actions', 'backups'];

            if (validTabs.indexOf(hash) !== -1) {
                $('.watchtower-tab-btn').removeClass('active');
                $('.watchtower-tab-btn[data-tab="' + hash + '"]').addClass('active');

                $('#mobile-tab-selector').val(hash);

                $('.watchtower-tab-content').hide();
                $('#tab-' + hash).show();

                if (hash === 'logs') {
                    setTimeout(function() {
                        loadAvailableLogs();
                        setTimeout(function() {
                            loadLogs();
                        }, 200);
                    }, 100);
                }

                if (hash === 'activity') {
                    setTimeout(function() {
                        loadActivityLogs();
                    }, 100);
                }

                if (hash === 'users') {
                    setTimeout(function() {
                        loadUsers();
                    }, 100);
                }

                if (hash === 'files') {
                    setTimeout(function() {
                        loadFiles('/');
                    }, 100);
                }

                if (hash === 'backups') {
                    setTimeout(function() {
                        loadBackups();
                    }, 100);
                }
            }
        }

        var currentFilePath = '/';

        $('#new-file-btn').on('click', function(e) {
            e.preventDefault();
            showCreateDialog('file');
            return false;
        });

        $('#new-directory-btn').on('click', function(e) {
            e.preventDefault();
            showCreateDialog('directory');
            return false;
        });

        function showCreateDialog(type) {
            var typeName = type === 'directory' ? 'Directory' : 'File';
            showPrompt('Enter ' + typeName.toLowerCase() + ' name:', '').then(function(name) {
                if (name && name.trim() !== '') {
                    createFileOrDirectory(type, name.trim());
                }
            });
        }

        function createFileOrDirectory(type, name) {
            var path = currentFilePath === '/' ? '/' + name : currentFilePath + '/' + name;

            console.log('Creating ' + type + ':', path);

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_create_file',
                site: context.siteUrl,
                path: path,
                type: type,
                nonce: context.nonce
            }, function(response) {
                if (response.success) {
                    loadFiles(currentFilePath);
                } else {
                    showAlert('Failed to create ' + type + ': ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                }
            }).fail(function() {
                showAlert('Network error occurred while creating ' + type);
            });
        }

        $(document).on('click', '.rename-file-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var row = $(this).closest('tr');
            var filePath = row.data('file-path');
            var fileName = row.data('file-name');

            if (!filePath) {
                showAlert('Could not determine file path');
                return;
            }

            showPrompt('Enter new name:', fileName).then(function(newName) {
                if (!newName || newName.trim() === '' || newName === fileName) {
                    return;
                }

                var pathParts = filePath.split('/');
                pathParts[pathParts.length - 1] = newName.trim();
                var newPath = pathParts.join('/');

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_rename_file',
                    site: context.siteUrl,
                    old_path: filePath,
                    new_path: newPath,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success) {
                        loadFiles(currentFilePath);
                    } else {
                        showAlert('Failed to rename: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                }).fail(function() {
                    showAlert('Network error occurred while renaming');
                });
            });
        });

        $(document).on('click', '.delete-file-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var row = $(this).closest('tr');
            var filePath = row.data('file-path');
            var fileName = row.data('file-name');
            var fileType = row.data('file-type');

            if (!filePath) {
                showAlert('Could not determine file path');
                return;
            }

            showConfirm('Delete ' + fileType.toLowerCase() + ' "' + fileName + '"?\n\nThis action cannot be undone.').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_delete_file',
                    site: context.siteUrl,
                    path: filePath,
                    nonce: context.nonce
                }, function(response) {
                    if (response.success) {
                        loadFiles(currentFilePath);
                    } else {
                        showAlert('Failed to delete: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                }).fail(function() {
                    showAlert('Network error occurred while deleting');
                });
            });
        });

        function loadFiles(path) {
            currentFilePath = path || '/';
            console.log('=== LOADING FILES ===');
            console.log('Path:', currentFilePath);
            console.log('Site URL:', context.siteUrl);
            console.log('Ajax URL:', context.ajaxurl);
            $('#file-tree-loading').show();
            $('#file-tree-container').hide();
            $('#file-tree-error').hide();

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_list_files',
                site: context.siteUrl,
                path: currentFilePath,
                nonce: context.nonce
            }, function(response) {
                console.log('=== FILES RESPONSE ===');
                console.log('Full response:', response);
                console.log('Response.data:', response.data);
                console.log('Response.data.items:', response.data ? response.data.items : 'no data');
                $('#file-tree-loading').hide();

                if (response.success && response.data) {
                    console.log('Rendering file tree with', response.data.items ? response.data.items.length : 0, 'items');
                    renderFileTree(response.data);
                    renderBreadcrumb(currentFilePath);
                    $('#file-tree-container').show();
                } else {
                    console.error('File load error:', response);
                    $('#file-tree-error-message').text(response.data && response.data.message ? response.data.message : 'Failed to load files');
                    $('#file-tree-error').show();
                }
            }).fail(function(xhr, status, error) {
                console.error('File load AJAX failed:', status, error);
                $('#file-tree-loading').hide();
                $('#file-tree-error-message').text('Network error occurred');
                $('#file-tree-error').show();
            });
        }

        var fileEditor = null;
        var currentEditingFilePath = null;

        function editFile(path) {
            console.log('Edit file:', path);
            currentEditingFilePath = path;

            $('#file-editor-status').text('Loading file...');
            $('#file-editor-modal').css('display', 'flex');

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_get_file_content',
                site: context.siteUrl,
                path: path,
                nonce: context.nonce
            }, function(response) {
                if (response.success && response.data) {
                    var fileName = path.split('/').pop();
                    $('#file-editor-title').text(fileName);
                    $('#file-editor-meta').text('Path: ' + path + ' | Size: ' + formatFileSize(response.data.size || 0));

                    if (!fileEditor) {
                        loadCodeMirror(function() {
                            initFileEditor(response.data.content, fileName);
                        });
                    } else {
                        fileEditor.setValue(response.data.content || '');
                        setEditorMode(fileName);
                    }

                    $('#file-editor-status').text('');
                } else {
                    $('#file-editor-status').text('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to load file'));
                }
            }).fail(function() {
                $('#file-editor-status').text('Network error: Failed to load file');
            });
        }

        function loadCodeMirror(callback) {
            if (window.CodeMirror) {
                callback();
                return;
            }

            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css';
            document.head.appendChild(link);

            var script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js';
            script.onload = function() {
                loadCodeMirrorModes(callback);
            };
            document.head.appendChild(script);
        }

        function loadCodeMirrorModes(callback) {
            var modes = [
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js'
            ];

            var loaded = 0;
            modes.forEach(function(src) {
                var script = document.createElement('script');
                script.src = src;
                script.onload = function() {
                    loaded++;
                    if (loaded === modes.length) {
                        callback();
                    }
                };
                document.head.appendChild(script);
            });
        }

        function initFileEditor(content, fileName) {
            fileEditor = CodeMirror.fromTextArea(document.getElementById('file-editor-content'), {
                lineNumbers: true,
                mode: 'text/plain',
                theme: 'default',
                indentUnit: 4,
                indentWithTabs: true,
                lineWrapping: true
            });

            fileEditor.setValue(content || '');
            setEditorMode(fileName);
        }

        function setEditorMode(fileName) {
            if (!fileEditor) return;

            var mode = 'text/plain';
            var ext = fileName.split('.').pop().toLowerCase();

            if (ext === 'js') mode = 'javascript';
            else if (ext === 'css') mode = 'css';
            else if (ext === 'html' || ext === 'htm') mode = 'htmlmixed';
            else if (ext === 'php') mode = 'application/x-httpd-php';
            else if (ext === 'json') mode = 'application/json';
            else if (ext === 'xml') mode = 'xml';

            fileEditor.setOption('mode', mode);
        }

        function saveFileContent() {
            if (!currentEditingFilePath || !fileEditor) return;

            var content = fileEditor.getValue();
            $('#file-editor-status').text('Saving...');
            $('#file-editor-save').prop('disabled', true);

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_save_file',
                site: context.siteUrl,
                path: currentEditingFilePath,
                content: content,
                nonce: context.nonce
            }, function(response) {
                if (response.success) {
                    $('#file-editor-status').text('Saved successfully!');
                    setTimeout(function() {
                        $('#file-editor-modal').hide();
                        $('#file-editor-status').text('');
                    }, 1000);
                } else {
                    $('#file-editor-status').text('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to save file'));
                }
                $('#file-editor-save').prop('disabled', false);
            }).fail(function() {
                $('#file-editor-status').text('Network error: Failed to save file');
                $('#file-editor-save').prop('disabled', false);
            });
        }

        $('#file-editor-save').on('click', function() {
            saveFileContent();
        });

        $('#file-editor-cancel, #file-editor-close').on('click', function() {
            $('#file-editor-modal').hide();
            currentEditingFilePath = null;
        });

        function renderBreadcrumb(path) {
            var $breadcrumb = $('#file-path-breadcrumb');
            $breadcrumb.empty();

            var parts = path.split('/').filter(function(p) { return p.length > 0; });

            $breadcrumb.append('<strong>Path:</strong> ');
            var $home = $('<a href="javascript:void(0)" data-path="/" style="color: #2271b1; text-decoration: none;">home</a>');
            $home.on('click', function(e) {
                e.preventDefault();
                loadFiles('/');
                return false;
            });
            $breadcrumb.append($home);

            var currentPath = '';
            parts.forEach(function(part, index) {
                $breadcrumb.append(' / ');
                currentPath += '/' + part;

                if (index === parts.length - 1) {
                    $breadcrumb.append('<span>' + escapeHtml(part) + '</span>');
                } else {
                    var $link = $('<a href="javascript:void(0)" data-path="' + currentPath + '" style="color: #2271b1; text-decoration: none;">' + escapeHtml(part) + '</a>');
                    (function(linkPath) {
                        $link.on('click', function(e) {
                            e.preventDefault();
                            loadFiles(linkPath);
                            return false;
                        });
                    })(currentPath);
                    $breadcrumb.append($link);
                }
            });
        }

        function renderFileTree(data) {
            var items = data.items || [];
            var $tree = $('#file-tree');

            $tree.empty();
            var $table = $('<table class="wp-list-table widefat fixed striped"></table>');
            var $thead = $('<thead><tr><th>Name</th><th>Size</th><th>Type</th><th>Permissions</th><th class="actions-column">Actions</th></tr></thead>');
            var $tbody = $('<tbody></tbody>');

            var totalSize = 0;
            items.forEach(function(item) {
                var $row = createFileTableRow(item);
                $tbody.append($row);
                if (item.type === 'file' && item.size) {
                    totalSize += item.size;
                }
            });

            $table.append($thead).append($tbody);
            $tree.append($table);

            $('#file-count').text(items.length);
            $('#file-total-size').text(formatFileSize(totalSize));
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
        }

        function createFileTableRow(item) {
            var isDirectory = item.type === 'directory';
            var $row = $('<tr class="file-tree-row"></tr>');
            $row.data('file-path', item.path);
            $row.data('file-name', item.name);
            $row.data('file-type', isDirectory ? 'Directory' : 'File');

            var $nameCell = $('<td></td>');
            var $nameContent = $('<div class="file-tree-name-cell"></div>');

            var iconClass = isDirectory ? 'dashicons-category' : 'dashicons-media-default';
            var $icon = $('<span class="file-tree-icon ' + (isDirectory ? 'directory' : 'file') + '"><span class="dashicons ' + iconClass + '"></span></span>');
            $nameContent.append($icon);

            var $link = $('<a href="javascript:void(0)" class="file-tree-name" style="color: #2271b1; text-decoration: none;">' + escapeHtml(item.name) + '</a>');
            $link.on('click', function(e) {
                e.preventDefault();
                if (isDirectory) {
                    loadFiles(item.path);
                } else {
                    editFile(item.path);
                }
                return false;
            });
            $nameContent.append($link);
            $nameCell.append($nameContent);

            var sizeStr = item.size !== null ? formatFileSize(item.size) : '-';
            var $sizeCell = $('<td>' + sizeStr + '</td>');

            var typeStr = isDirectory ? 'Directory' : 'File';
            var $typeCell = $('<td>' + typeStr + '</td>');

            var permsStr = item.permissions || '-';
            var $permsCell = $('<td>' + permsStr + '</td>');

            var $actionsCell = $('<td class="actions-column file-actions-cell"></td>');
            var $renameBtn = $('<button type="button" class="button rename-file-btn"><span class="dashicons dashicons-randomize"></span><span class="button-text">Rename</span></button>');
            var $deleteBtn = $('<button type="button" class="button button-link-delete delete-file-btn"><span class="dashicons dashicons-trash"></span><span class="button-text">Delete</span></button>');
            $actionsCell.append($renameBtn).append($deleteBtn);

            $row.append($nameCell).append($sizeCell).append($typeCell).append($permsCell).append($actionsCell);
            return $row;
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function handleTabOverflow() {
            var $wrapper = $('.watchtower-tabs-wrapper');
            var $tabs = $('.watchtower-tabs');
            var $overflow = $('.watchtower-tabs-overflow');
            var $overflowMenu = $('.watchtower-overflow-menu');
            var $tabButtons = $('.watchtower-tabs > .watchtower-tab-btn');

            if ($(window).width() <= 782) {
                $overflow.hide();
                $tabButtons.show();
                $overflowMenu.empty();
                return;
            }

            var wrapperWidth = $wrapper.width();
            var availableWidth = wrapperWidth - 150;
            var totalWidth = 0;
            var overflowTabs = [];

            $tabButtons.each(function(index) {
                totalWidth += $(this).outerWidth(true);
                if (totalWidth > availableWidth) {
                    overflowTabs.push($(this));
                }
            });

            if (overflowTabs.length > 0) {
                $overflow.show();
                $overflowMenu.empty();

                overflowTabs.forEach(function($tab) {
                    var $clone = $tab.clone(true);
                    $overflowMenu.append($clone);
                    $tab.hide();
                });
            } else {
                $overflow.hide();
                $tabButtons.show();
                $overflowMenu.empty();
            }
        }

        $('.watchtower-overflow-btn').on('click', function(e) {
            e.stopPropagation();
            $('.watchtower-overflow-menu').toggleClass('show');
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.watchtower-tabs-overflow').length) {
                $('.watchtower-overflow-menu').removeClass('show');
            }
        });

        $(window).on('resize', function() {
            handleTabOverflow();
        });

        handleTabOverflow();

        $('#maintenance-mode-toggle').on('change', function() {
            const checkbox = $(this);
            const isChecked = checkbox.is(':checked');
            const siteUrl = checkbox.data('site-url');
            const toggleSwitch = checkbox.siblings('.maintenance-toggle-switch');
            const toggleLabel = checkbox.siblings('.maintenance-mode-label');

            toggleSwitch.addClass(isChecked ? 'maintenance' : 'live').removeClass(isChecked ? 'live' : 'maintenance');
            toggleLabel.text(isChecked ? 'Maintenance' : 'Live');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'watchtower_manager_toggle_maintenance',
                    site_url: siteUrl,
                    enabled: isChecked,
                    nonce: context.nonce
                },
                success: function(response) {
                    console.log('AJAX Response:', response);
                    if (response.success) {
                        console.log('Maintenance mode ' + (isChecked ? 'enabled' : 'disabled'));

                        const $healthCard = $('.health-status-card');
                        const $healthIcon = $('.health-status-icon');
                        const $healthSubtitle = $('.health-status-subtitle');
                        const $healthText = $('.health-status-text');

                        if (isChecked) {
                            $healthCard.removeClass('healthy critical').addClass('warning');
                            $healthIcon.removeClass('dashicons-yes-alt dashicons-dismiss').addClass('dashicons-admin-tools warning');
                            $healthSubtitle.removeClass('healthy critical').addClass('warning');
                            $healthText.text('Site in Maintenance');
                        } else {
                            $healthCard.removeClass('warning critical').addClass('healthy');
                            $healthIcon.removeClass('dashicons-admin-tools dashicons-dismiss warning').addClass('dashicons-yes-alt healthy');
                            $healthSubtitle.removeClass('warning critical').addClass('healthy');
                            $healthText.text('Site is Healthy');
                        }
                    } else {
                        console.error('Failed to toggle:', response);
                        checkbox.prop('checked', !isChecked);
                        toggleSwitch.removeClass(isChecked ? 'maintenance' : 'live').addClass(isChecked ? 'live' : 'maintenance');
                        toggleLabel.text(isChecked ? 'Live' : 'Maintenance');
                        showAlert('Failed to toggle maintenance mode: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr, status, error);
                    checkbox.prop('checked', !isChecked);
                    toggleSwitch.removeClass(isChecked ? 'maintenance' : 'live').addClass(isChecked ? 'live' : 'maintenance');
                    toggleLabel.text(isChecked ? 'Live' : 'Maintenance');
                    showAlert('Failed to toggle maintenance mode: ' + error);
                }
            });
        });

        function showPluginDetails(plugin) {
            var statusBadge = plugin.active
                ? '<span style="background: #d5f3e5; color: #00a32a; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600;">Active</span>'
                : '<span style="background: #f0f0f1; color: #646970; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600;">Inactive</span>';

            var updateBadge = plugin.update_available
                ? '<span style="background: #fcf0e3; color: #996800; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 6px;">Update Available</span>'
                : '';

            $('#plugin-dialog-title').html('<span style="display: flex; justify-content: space-between; align-items: center; width: 100%;"><span>' + plugin.name + '</span><span>' + statusBadge + updateBadge + '</span></span>');

            var html = '<div style="line-height: 1.8;">';
            html += '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';

            var rows = [
                ['Slug', '<code>' + (plugin.slug || '-') + '</code>'],
                ['Version', plugin.version || '-'],
                ['Author', plugin.author || '-'],
                ['Requires WordPress', plugin.requires_wp || '-'],
                ['Requires PHP', plugin.requires_php || '-']
            ];

            if (plugin.plugin_uri) {
                rows.push(['Plugin URL', '<a href="' + plugin.plugin_uri + '" target="_blank">' + plugin.plugin_uri + '</a>']);
            }

            if (plugin.wp_org && plugin.wp_org.url) {
                rows.push(['WordPress.org', '<a href="' + plugin.wp_org.url + '" target="_blank">View on WordPress.org</a>']);
            }

            rows.forEach(function(row) {
                html += '<tr>';
                html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600; width: 140px; color: #1d2327;">' + row[0] + '</td>';
                html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; color: #50575e;">' + row[1] + '</td>';
                html += '</tr>';
            });

            html += '</table>';

            if (plugin.description) {
                html += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ccd0d4;">';
                html += '<strong style="display: block; margin-bottom: 8px;">Description</strong>';
                html += '<div style="color: #50575e; font-size: 13px;">' + plugin.description + '</div>';
                html += '</div>';
            }

            html += '</div>';

            $('#plugin-dialog-body').html(html);
            $('#plugin-details-dialog').show();
        }

        $(document).on('click', '.plugin-details-btn', function(e) {
            e.stopPropagation();
            var plugin = $(this).closest('tr').data('plugin');
            if (plugin) {
                showPluginDetails(plugin);
            }
        });

        $(document).on('click', '.plugin-row', function(e) {
            if ($(window).width() <= 782 && !$(e.target).is('button')) {
                var plugin = $(this).data('plugin');
                if (plugin) {
                    showPluginDetails(plugin);
                }
            }
        });

        $('#plugin-dialog-close').on('click', function() {
            $('#plugin-details-dialog').hide();
        });

        $('#plugin-details-dialog').on('click', function(e) {
            if ($(e.target).hasClass('watchtower-dialog-overlay')) {
                $(this).hide();
            }
        });

        setTimeout(function() {
            $('#watchtower-page-loading').fadeOut(300, function() {
                $(this).remove();
            });
            $('#watchtower-page-content').css('opacity', '1');
        }, 500);
    });
