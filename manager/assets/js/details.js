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
            var siteUrl = button.data('site-url');

            if (!confirm('Are you sure you want to update the agent plugin on this site?')) {
                return;
            }

            button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; display: inline-block; transform-origin: center center; animation: rotation 2s infinite linear;"></span> Updating...');

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_update_agent',
                site_url: siteUrl,
                nonce: context.nonce
            }, function(response) {
                if (response.success) {
                    button.html('<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span> Update Successful!').css('color', '#00a32a');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    alert('Update failed: ' + (response.data.message || 'Unknown error'));
                    button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="margin-top: 3px;"></span> Update Remote Agent');
                }
            }).fail(function() {
                alert('Update request failed. Please try again.');
                button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="margin-top: 3px;"></span> Update Remote Agent');
            });
        });

        $('.watchtower-toggle-debug-btn').on('click', function() {
            var button = $(this);
            var siteUrl = button.data('site-url');
            var debugEnabled = button.data('debug-enabled') === 1;
            var newState = !debugEnabled;

            if (!confirm('Are you sure you want to ' + (newState ? 'enable' : 'disable') + ' debug mode on this site?')) {
                return;
            }

            button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; display: inline-block; transform-origin: center center; animation: rotation 2s infinite linear;"></span> ' + (newState ? 'Enabling' : 'Disabling') + '...');

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_toggle_debug',
                site_url: siteUrl,
                enabled: newState,
                nonce: context.nonce
            }, function(response) {
                if (response.success) {
                    button.html('<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span> ' + (newState ? 'Debug Enabled!' : 'Debug Disabled!')).css('color', '#00a32a');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    alert('Failed to toggle debug mode: ' + (response.data.message || 'Unknown error'));
                    button.prop('disabled', false).html('<span class="dashicons dashicons-' + (debugEnabled ? 'no' : 'yes') + '" style="margin-top: 3px;"></span> ' + (debugEnabled ? 'Disable' : 'Enable') + ' Debug');
                }
            }).fail(function() {
                alert('Request failed. Please try again.');
                button.prop('disabled', false).html('<span class="dashicons dashicons-' + (debugEnabled ? 'no' : 'yes') + '" style="margin-top: 3px;"></span> ' + (debugEnabled ? 'Disable' : 'Enable') + ' Debug');
            });
        });

        $('.watchtower-scan-btn').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var siteUrl = button.data('site-url');
            var originalHtml = button.html();

            button.html('<span class="dashicons dashicons-update" style="margin-top: 3px; display: inline-block; transform-origin: center center; animation: rotation 2s infinite linear;"></span> Scanning...').prop('disabled', true);

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_scan_agent',
                site_url: siteUrl,
                nonce: context.nonce
            }, function(response) {
                if (response.success) {
                    button.html('<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span> Done!');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    alert('Scan failed: ' + response.data.message);
                    button.html(originalHtml).prop('disabled', false);
                }
            }).fail(function() {
                alert('Scan request failed. Please try again.');
                button.html(originalHtml).prop('disabled', false);
            });
        });

        $('.watchtower-tab-btn').on('click', function() {
            var tab = $(this).data('tab');

            $('.watchtower-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('#mobile-tab-selector').val(tab);

            $('.watchtower-tab-content').hide();
            $('#tab-' + tab).show();

            window.location.hash = '#' + tab;

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
        });

        function loadBackups() {
            $('#backups-loading').show();
            $('#backups-container').hide();
            $('#backups-empty').hide();
            $('#backups-error').hide();

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_get_backups',
                site_url: context.siteUrl,
                nonce: context.nonce
            }, function(response) {
                $('#backups-loading').hide();

                if (response.success && response.data.success) {
                    var backups = response.data.backups || [];

                    if (backups.length > 0) {
                        var tbody = $('#backups-table-body');
                        tbody.empty();

                        backups.forEach(function(backup) {
                            var componentsHtml = backup.components.map(function(comp) {
                                return '<span class="backup-component-badge">' + comp + '</span>';
                            }).join(' ');

                            var sizeText = backup.size ? formatBytes(backup.size) : '-';

                            var row = $('<tr></tr>');
                            row.append('<td><strong>' + backup.date + '</strong><br><small>ID: ' + backup.id + '</small></td>');
                            row.append('<td style="text-align: center; vertical-align: middle;">' + sizeText + '</td>');
                            row.append('<td style="text-align: center; vertical-align: middle;">' + componentsHtml + '</td>');
                            row.append('<td style="text-align: right; vertical-align: middle;">' +
                                '<button class="button button-small restore-backup-btn" data-backup-id="' + backup.id + '" data-backup-date="' + backup.date + '">' +
                                '<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Restore' +
                                '</button> ' +
                                '<button class="button button-small button-link-delete delete-backup-btn" data-backup-id="' + backup.id + '" data-backup-date="' + backup.date + '">' +
                                '<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> Delete' +
                                '</button>' +
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

            if (!confirm('Create a new backup? This may take several minutes.')) {
                return;
            }

            button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; display: inline-block; transform-origin: center center; animation: rotation 2s infinite linear;"></span> Creating...');

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_create_backup',
                site_url: context.siteUrl,
                nonce: context.nonce
            }, function(response) {
                if (response.success) {
                    button.html('<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span> Backup Started!').css('color', '#00a32a');
                    setTimeout(function() {
                        button.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span> Backup Now').css('color', '');
                        $('#backups-table').data('loaded', false);
                        loadBackups();
                    }, 2000);
                } else {
                    alert('Failed to create backup: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    button.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span> Backup Now');
                }
            }).fail(function() {
                alert('Network error while creating backup');
                button.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span> Backup Now');
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

            if (!confirm('Restore backup from ' + backupDate + '?\n\nWARNING: This will overwrite your current site data. The site may be temporarily unavailable during restore.')) {
                return;
            }

            $('#restore-progress-modal').css('display', 'flex');
            $('#restore-progress-message').text('Starting restore...');
            $('#restore-progress-bar').css('width', '0%');
            $('#restore-progress-percent').text('0%');
            $('#restore-progress-close').hide();

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_restore_backup',
                site_url: context.siteUrl,
                backup_id: backupId,
                nonce: context.nonce
            }, function(response) {
                if (response.success) {
                    pollRestoreProgress();
                } else {
                    $('#restore-progress-message').html('<span style="color: #d63638;">Failed: ' + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</span>');
                    $('#restore-progress-close').show();
                }
            }).fail(function() {
                $('#restore-progress-message').html('<span style="color: #d63638;">Network error while starting restore</span>');
                $('#restore-progress-close').show();
            });
        });

        var restoreProgressInterval;
        function pollRestoreProgress() {
            restoreProgressInterval = setInterval(function() {
                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_get_restore_status',
                    site_url: context.siteUrl,
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

        $(document).on('click', '.delete-backup-btn', function() {
            var button = $(this);
            var backupId = button.data('backup-id');
            var backupDate = button.data('backup-date');

            if (!confirm('Delete backup from ' + backupDate + '? This cannot be undone.')) {
                return;
            }

            button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; display: inline-block; transform-origin: center center; animation: rotation 2s infinite linear;"></span> Deleting...');

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_delete_backup',
                site_url: context.siteUrl,
                backup_id: backupId,
                nonce: context.nonce
            }, function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        if ($('#backups-table-body tr').length === 0) {
                            $('#backups-container').hide();
                            $('#backups-empty').show();
                        }
                    });
                } else {
                    alert('Failed to delete backup: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    button.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> Delete');
                }
            }).fail(function() {
                alert('Network error while deleting backup');
                button.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> Delete');
            });
        });

        function loadAvailableLogs() {
            $.post(context.ajaxurl, {
                action: 'watchtower_manager_get_available_logs',
                site_url: context.siteUrl,
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
                site_url: context.siteUrl,
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
                site_url: activitySiteUrl,
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
            var html = '<div style="position: relative;">';
            html += '<table style="width: 100%; border-collapse: collapse;">';
            html += '<thead><tr style="background: #f0f0f1; position: sticky; top: 0; z-index: 10;">';
            html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ccc; font-weight: 600; background: #f0f0f1; width: 180px; min-width: 180px;">Time</th>';
            html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ccc; font-weight: 600; background: #f0f0f1;">Action</th>';
            html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ccc; font-weight: 600; background: #f0f0f1;">Actor</th>';
            html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ccc; font-weight: 600; background: #f0f0f1;">IP Address</th>';
            html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ccc; font-weight: 600; background: #f0f0f1;">Details</th>';
            html += '</tr></thead><tbody>';

            entries.forEach(function(entry, index) {
                var rowStyle = index % 2 === 0 ? 'background: #fff;' : 'background: #f9f9f9;';
                html += '<tr style="' + rowStyle + '">';
                html += '<td style="padding: 8px; border-bottom: 1px solid #eee; font-size: 12px; width: 180px; min-width: 180px; white-space: nowrap;">' + escapeHtml(entry.timestamp) + '</td>';
                html += '<td style="padding: 8px; border-bottom: 1px solid #eee;"><span style="background: #e3f2fd; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 500;">' + escapeHtml(entry.action) + '</span></td>';
                html += '<td style="padding: 8px; border-bottom: 1px solid #eee; font-size: 12px;">' + escapeHtml(entry.actor_name) + '</td>';
                html += '<td style="padding: 8px; border-bottom: 1px solid #eee; font-size: 12px;">' + escapeHtml(entry.ip_address) + '</td>';
                html += '<td style="padding: 8px; border-bottom: 1px solid #eee; font-size: 12px; color: #2c3338; line-height: 1.6;">' + formatDetails(entry.details) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '</div>';
            $('#activity-log-viewer').html(html);
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
                    site_url: context.siteUrl,
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
                    alert('Please fill in all required fields (Username, Email, Password)');
                    return;
                }

                $(this).prop('disabled', true).text('Creating...');

                $.post(context.ajaxurl, {
                    action: 'watchtower_manager_create_user',
                    site_url: context.siteUrl,
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
                        alert('Failed to create user: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                    $('#confirm-create-user-btn').prop('disabled', false).text('Create User');
                }).fail(function() {
                    alert('Network error occurred');
                    $('#confirm-create-user-btn').prop('disabled', false).text('Create User');
                });
            } else {
                if (!email) {
                    alert('Email cannot be empty');
                    return;
                }

                $(this).prop('disabled', true).text('Updating...');

                var updateData = {
                    action: 'watchtower_manager_update_user',
                    site_url: context.siteUrl,
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
                        alert('User updated successfully');
                        loadUsers();
                    } else {
                        alert('Failed to update user: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                    $('#confirm-create-user-btn').prop('disabled', false).text('Update User');
                }).fail(function() {
                    alert('Network error occurred');
                    $('#confirm-create-user-btn').prop('disabled', false).text('Update User');
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
            $('#confirm-create-user-btn').text('Update User').data('user-id', userId).data('mode', 'edit');
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
                alert('Error: Could not find user row');
                return;
            }

            var email = row.find('.user-email-edit').val().trim();
            var firstName = row.find('.user-first-name-edit').val().trim();
            var lastName = row.find('.user-last-name-edit').val().trim();
            var role = row.find('.user-role-edit').val();

            if (!email) {
                alert('Email cannot be empty');
                return;
            }

            $(this).prop('disabled', true).text('Saving...');

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_update_user',
                site_url: context.siteUrl,
                nonce: context.nonce,
                user_id: userId,
                email: email,
                first_name: firstName,
                last_name: lastName,
                role: role
            }, function(response) {
                if (response.success) {
                    alert('User updated successfully');
                } else {
                    alert('Failed to update user: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                }
                loadUsers();
            }).fail(function() {
                alert('Network error occurred');
                loadUsers();
            });
        });

        $(document).on('click', '.reset-password-item', function(e) {
            e.preventDefault();
            var userId = $(this).data('user-id');
            var userEmail = $(this).data('user-email');

            $('.edit-dropdown-menu').hide();

            if (!confirm('Send password reset email to ' + userEmail + '?')) {
                return;
            }

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_reset_password',
                site_url: context.siteUrl,
                nonce: context.nonce,
                user_id: userId
            }, function(response) {
                if (response.success) {
                    alert('Password reset email sent successfully');
                } else {
                    alert('Failed to send password reset: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                }
                loadUsers();
            }).fail(function() {
                alert('Network error occurred');
                loadUsers();
            });
        });

        $(document).on('click', '.delete-user-btn', function() {
            var userId = $(this).data('user-id');
            var username = $(this).data('username');

            if (!confirm('Are you sure you want to delete user "' + username + '"? This action cannot be undone.')) {
                return;
            }

            $(this).prop('disabled', true).text('Deleting...');

            $.post(context.ajaxurl, {
                action: 'watchtower_manager_delete_user',
                site_url: context.siteUrl,
                nonce: context.nonce,
                user_id: userId
            }, function(response) {
                if (response.success) {
                    loadUsers();
                } else {
                    alert('Failed to delete user: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    loadUsers();
                }
            }).fail(function() {
                alert('Network error occurred');
                loadUsers();
            });
        });

        if (window.location.hash) {
            var hash = window.location.hash.substring(1);
            var validTabs = ['overview', 'plugins', 'users', 'activity', 'logs', 'actions', 'backups'];

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
            }
        }

        setTimeout(function() {
            $('#watchtower-page-loading').fadeOut(300, function() {
                $(this).remove();
            });
            $('#watchtower-page-content').css('opacity', '1');
        }, 500);
    });
