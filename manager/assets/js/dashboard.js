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

            $('#site-search').val('');
            $('#search-autocomplete').hide();
            var url = new URL(window.location);
            url.searchParams.delete('search');
            window.history.replaceState({}, '', url);
            restoreOriginalStats();

            if (currentFilter === filterType) {
                currentFilter = null;
                $('.filter-card').removeClass('filter-active');
                $('.site-row').show();
                sortRowsByName();
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

        var originalStats = null;

        function updateStatCards() {
            var $ = jQuery;
            var total = 0, healthy = 0, unhealthy = 0;

            $('.site-row:visible').each(function() {
                total++;
                var status = $(this).data('health-status');
                if (status === 'healthy') {
                    healthy++;
                } else if (status === 'warning' || status === 'critical') {
                    unhealthy++;
                }
            });

            $('.stat-card[data-filter="all"] .stat-value').text(total);
            $('.stat-card[data-filter="healthy"] .stat-value').text(healthy);
            $('.stat-card[data-filter="unhealthy"] .stat-value').text(unhealthy);
        }

        function saveOriginalStats() {
            var $ = jQuery;
            if (originalStats) return;
            originalStats = {
                total: $('.stat-card[data-filter="all"] .stat-value').text(),
                healthy: $('.stat-card[data-filter="healthy"] .stat-value').text(),
                unhealthy: $('.stat-card[data-filter="unhealthy"] .stat-value').text()
            };
        }

        function restoreOriginalStats() {
            var $ = jQuery;
            if (!originalStats) return;
            $('.stat-card[data-filter="all"] .stat-value').text(originalStats.total);
            $('.stat-card[data-filter="healthy"] .stat-value').text(originalStats.healthy);
            $('.stat-card[data-filter="unhealthy"] .stat-value').text(originalStats.unhealthy);
        }

        function healthPriority(status) {
            if (status === 'critical') return 0;
            if (status === 'warning') return 1;
            return 2;
        }

        function sortRowsByName() {
            var $ = jQuery;

            var $table = $('.sites-table tbody');
            if ($table.length) {
                var rows = $table.find('tr.site-row').get();
                rows.sort(function(a, b) {
                    var aPriority = healthPriority($(a).data('health-status') || '');
                    var bPriority = healthPriority($(b).data('health-status') || '');
                    if (aPriority !== bPriority) return aPriority - bPriority;
                    var aName = ($(a).data('site-name') || '').toString();
                    var bName = ($(b).data('site-name') || '').toString();
                    return aName.localeCompare(bName);
                });
                $.each(rows, function(idx, row) { $table.append(row); });
            }

            var $grid = $('.mobile-sites-grid');
            if ($grid.length) {
                var tiles = $grid.find('.mobile-site-tile.site-row').get();
                tiles.sort(function(a, b) {
                    var aPriority = healthPriority($(a).data('health-status') || '');
                    var bPriority = healthPriority($(b).data('health-status') || '');
                    if (aPriority !== bPriority) return aPriority - bPriority;
                    var aName = ($(a).data('site-name') || '').toString();
                    var bName = ($(b).data('site-name') || '').toString();
                    return aName.localeCompare(bName);
                });
                $.each(tiles, function(idx, tile) { $grid.append(tile); });
            }

            $('.sort-header').removeClass('sort-active').find('.sort-indicator').remove();
            currentSortColumn = 'name';
            currentSortDir = 'asc';
        }

        var categoryMap = {
            'plugins': 'search-plugins',
            'plugin': 'search-plugins',
            'tags': 'search-tags',
            'tag': 'search-tags',
            'users': 'search-users',
            'user': 'search-users',
            'theme': 'search-theme',
            'themes': 'search-theme',
            'settings': 'search-settings',
            'setting': 'search-settings',
            'name': 'site-name',
            'url': 'site-url'
        };

        function parseQuery(raw) {
            var category = null;
            var negate = false;
            var term = raw.toLowerCase().trim();

            var colonIdx = term.indexOf(':');
            if (colonIdx > 0) {
                var prefix = term.substring(0, colonIdx);
                if (categoryMap[prefix]) {
                    category = categoryMap[prefix];
                    term = term.substring(colonIdx + 1);
                }
            }

            if (term.charAt(0) === '!') {
                negate = true;
                term = term.substring(1);
            }

            return { category: category, negate: negate, term: term };
        }

        function scoreSite($row, parsed) {
            var $ = jQuery;
            var term = parsed.term;

            if (!term) return parsed.negate ? 0 : 50;

            if (parsed.category) {
                var fieldVal = ($row.data(parsed.category) || '').toString();
                var found = fieldVal.indexOf(term) !== -1;
                if (parsed.negate) {
                    return found ? 0 : 50;
                }
                return found ? 60 : 0;
            }

            var siteName = ($row.data('site-name') || '').toString();
            var siteUrl = ($row.data('site-url') || '').toString();
            var searchText = ($row.data('search-text') || '').toString();

            if (parsed.negate) {
                return searchText.indexOf(term) !== -1 ? 0 : 50;
            }

            if (siteName === term) return 100;
            if (siteName.indexOf(term) === 0) return 90;
            if (siteName.indexOf(term) !== -1) return 80;
            if (siteUrl.indexOf(term) !== -1) return 70;
            if (searchText.indexOf(term) !== -1) return 50;
            return 0;
        }

        function searchSites(query) {
            var $ = jQuery;
            query = (query || '').trim();

            saveOriginalStats();

            if (!query) {
                $('.site-row').show();
                $('.search-no-results').remove();
                restoreOriginalStats();
                sortRowsByName();
                return;
            }

            currentFilter = null;
            $('.filter-card').removeClass('filter-active');
            currentSortColumn = 'name';
            currentSortDir = 'asc';
            jQuery('.sort-header').removeClass('sort-active').find('.sort-indicator').remove();

            var parsed = parseQuery(query);

            $('.site-row').each(function() {
                var score = scoreSite($(this), parsed);
                $(this).data('search-score', score);

                if (score > 0) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });

            var $table = $('.sites-table tbody');
            if ($table.length) {
                var rows = $table.find('tr.site-row').get();
                rows.sort(function(a, b) {
                    return ($(b).data('search-score') || 0) - ($(a).data('search-score') || 0);
                });
                $.each(rows, function(idx, row) {
                    $table.append(row);
                });
            }

            var $grid = $('.mobile-sites-grid');
            if ($grid.length) {
                var tiles = $grid.find('.mobile-site-tile.site-row').get();
                tiles.sort(function(a, b) {
                    return ($(b).data('search-score') || 0) - ($(a).data('search-score') || 0);
                });
                $.each(tiles, function(idx, tile) {
                    $grid.append(tile);
                });
            }

            updateStatCards();

            $('.search-no-results').remove();
            if ($('.site-row:visible').length === 0) {
                var msg = '<tr class="search-no-results"><td colspan="8" style="text-align: center; padding: 40px 20px; color: #646970;">' +
                    '<span class="dashicons dashicons-search" style="font-size: 48px; width: 48px; height: 48px; color: #c3c4c7; display: block; margin: 0 auto 12px;"></span>' +
                    'No matching sites found</td></tr>';
                var mobileMsg = '<div class="search-no-results" style="text-align: center; padding: 40px 20px; color: #646970;">' +
                    '<span class="dashicons dashicons-search" style="font-size: 48px; width: 48px; height: 48px; color: #c3c4c7; display: block; margin: 0 auto 12px;"></span>' +
                    'No matching sites found</div>';
                $('.sites-table tbody').append(msg);
                $('.mobile-sites-grid').append(mobileMsg);
            }
        }

        var currentSortColumn = 'name';
        var currentSortDir = 'asc';

        function sortTableByColumn(column, dir) {
            var $ = jQuery;
            var $table = $('.sites-table tbody');
            if (!$table.length) return;

            var rows = $table.find('tr.site-row').get();
            rows.sort(function(a, b) {
                var aVal = ($(a).data('sort-' + column) || '').toString();
                var bVal = ($(b).data('sort-' + column) || '').toString();

                if (column === 'scanned') {
                    var aNum = parseFloat(aVal) || 999999999;
                    var bNum = parseFloat(bVal) || 999999999;
                    return dir === 'asc' ? aNum - bNum : bNum - aNum;
                }

                var cmp = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                return dir === 'asc' ? cmp : -cmp;
            });

            $.each(rows, function(idx, row) { $table.append(row); });

            $('.sort-header').removeClass('sort-active').find('.sort-indicator').remove();
            var indicator = dir === 'asc' ? '\u25B2' : '\u25BC';
            $('.sort-header[data-sort="' + column + '"]')
                .addClass('sort-active')
                .append('<span class="sort-indicator">' + indicator + '</span>');
        }

        jQuery(document).ready(function($) {
            $(document).on('click', '.sort-header', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var column = $(this).data('sort');
                if (currentSortColumn === column) {
                    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSortColumn = column;
                    currentSortDir = 'asc';
                }
                sortTableByColumn(currentSortColumn, currentSortDir);
            });

            $(document).on('click', '.tag-pill-filter', function(e) {
                e.stopPropagation();
                var tag = $(this).data('tag');
                var query = 'tags:' + tag;
                var current = $('#site-search').val().trim();
                if (current === query) {
                    $('#site-search').val('');
                    $('#site-search-clear').hide();
                    searchSites('');
                    var url = new URL(window.location);
                    url.searchParams.delete('search');
                    window.history.replaceState({}, '', url);
                    return;
                }
                $('#site-search').val(query);
                $('#site-search-clear').show();
                searchSites(query);
                var url = new URL(window.location);
                url.searchParams.set('search', query);
                window.history.replaceState({}, '', url);
            });

            $('.clickable-row').on('click', function(e) {
                if ($(e.target).closest('a, button, .tag-pill-filter').length) {
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

            var searchAutoIndex = -1;

            $('#site-search').on('input', function() {
                var raw = $(this).val().trim();
                $('#site-search-clear').toggle(raw.length > 0);
                searchSites(raw);
                updateSearchAutocomplete(raw);

                var url = new URL(window.location);
                if (raw) {
                    url.searchParams.set('search', raw);
                } else {
                    url.searchParams.delete('search');
                }
                window.history.replaceState({}, '', url);
            });

            $('#site-search-clear').on('click', function() {
                $('#site-search').val('').trigger('input').focus();
            });

            $('#site-search').on('keydown', function(e) {
                var $dropdown = $('#search-autocomplete');
                var $items = $dropdown.find('.search-autocomplete-item');

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    searchAutoIndex = Math.min(searchAutoIndex + 1, $items.length - 1);
                    $items.removeClass('active');
                    $items.eq(searchAutoIndex).addClass('active');
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    searchAutoIndex = Math.max(searchAutoIndex - 1, 0);
                    $items.removeClass('active');
                    $items.eq(searchAutoIndex).addClass('active');
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (searchAutoIndex >= 0 && $items.eq(searchAutoIndex).length) {
                        var text = $items.eq(searchAutoIndex).data('fill');
                        $('#site-search').val(text);
                        searchSites(text);
                        var url = new URL(window.location);
                        url.searchParams.set('search', text);
                        window.history.replaceState({}, '', url);
                    }
                    $dropdown.hide();
                    searchAutoIndex = -1;
                } else if (e.key === 'Escape') {
                    $dropdown.hide();
                    searchAutoIndex = -1;
                }
            });

            $('#site-search').on('blur', function() {
                setTimeout(function() {
                    $('#search-autocomplete').hide();
                    searchAutoIndex = -1;
                }, 200);
            });

            $(document).on('click', '.search-autocomplete-item', function() {
                var text = $(this).data('fill');
                $('#site-search').val(text);
                searchSites(text);
                $('#search-autocomplete').hide();
                searchAutoIndex = -1;
                var url = new URL(window.location);
                url.searchParams.set('search', text);
                window.history.replaceState({}, '', url);
            });

            var categoryToTermsKey = {
                'plugins': 'plugins',
                'plugin': 'plugins',
                'tags': 'tags',
                'tag': 'tags',
                'users': 'users',
                'user': 'users',
                'theme': 'themes',
                'themes': 'themes',
                'name': 'names',
                'url': 'urls'
            };

            function updateSearchAutocomplete(raw) {
                var $dropdown = $('#search-autocomplete');
                searchAutoIndex = -1;

                if (!raw || raw.trim().length < 1) {
                    $dropdown.hide();
                    return;
                }

                var parsed = parseQuery(raw);
                var searchTerm = parsed.term;

                if (parsed.negate || !searchTerm) {
                    $dropdown.hide();
                    return;
                }

                var prefix = '';
                var termsKey = 'all';
                var colonIdx = raw.indexOf(':');
                if (colonIdx > 0) {
                    var catPrefix = raw.substring(0, colonIdx).toLowerCase();
                    if (categoryMap[catPrefix]) {
                        prefix = raw.substring(0, colonIdx + 1);
                        termsKey = categoryToTermsKey[catPrefix] || 'all';
                    }
                }

                var searchTerms = context.searchTerms || {};
                var terms = searchTerms[termsKey] || searchTerms.all || [];
                var matches = [];

                for (var i = 0; i < terms.length; i++) {
                    var term = terms[i];
                    var lower = term.toLowerCase();
                    if (lower.indexOf(searchTerm) !== -1) {
                        var priority = lower.indexOf(searchTerm) === 0 ? 0 : 1;
                        matches.push({ term: term, fill: prefix + term, priority: priority });
                    }
                }

                matches.sort(function(a, b) {
                    if (a.priority !== b.priority) return a.priority - b.priority;
                    return a.term.localeCompare(b.term);
                });

                matches = matches.slice(0, 8);

                if (matches.length === 0) {
                    $dropdown.hide();
                    return;
                }

                $dropdown.empty();
                matches.forEach(function(m) {
                    var $item = $('<div class="search-autocomplete-item"></div>').text(m.term).attr('data-fill', m.fill);
                    $dropdown.append($item);
                });
                $dropdown.show();
            }

            var urlParams = new URLSearchParams(window.location.search);
            var initialSearch = urlParams.get('search') || '';
            if (initialSearch) {
                $('#site-search').val(initialSearch);
                $('#site-search-clear').show();
                searchSites(initialSearch);
            }

            setTimeout(function() {
                $('#watchtower-dashboard-loading').fadeOut(300, function() {
                    $(this).remove();
                });
                $('#watchtower-dashboard-content').css('opacity', '1');
            }, 500);
        });
