var WatchtowerPluginDialog = (function($) {
    var state = { slug: null, catalog: null, editMode: false };

    var TAB_STYLE = '<style>' +
        '.pd-tabs { display: flex; border-bottom: 1px solid #ccd0d4; margin: -15px -15px 15px -15px; padding: 0 15px; }' +
        '.pd-tab { padding: 10px 16px; cursor: pointer; font-size: 13px; font-weight: 500; color: #50575e; border-bottom: 2px solid transparent; margin-bottom: -1px; background: none; border-top: none; border-left: none; border-right: none; }' +
        '.pd-tab:hover { color: #1d2327; }' +
        '.pd-tab.active { color: #2271b1; border-bottom-color: #2271b1; }' +
        '.pd-tab-content { display: none; }' +
        '.pd-tab-content.active { display: block; }' +
        '</style>';

    var STATUS_COLORS = {
        active: { bg: '#d5f3e5', color: '#00a32a' },
        inactive: { bg: '#f0f0f1', color: '#646970' },
        expired: { bg: '#fce2e2', color: '#cc1818' },
        revoked: { bg: '#fcf0e3', color: '#996800' }
    };

    function escapeAttr(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function buildOverviewHtml(plugin, catalog) {
        var html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        var rows = [
            ['Slug', '<code>' + escapeAttr(plugin.slug || '-') + '</code>']
        ];

        var version = plugin.version || null;
        if (!version && plugin.versions) {
            var vkeys = Object.keys(plugin.versions).sort();
            if (vkeys.length === 1) {
                version = vkeys[0];
            } else if (vkeys.length > 1) {
                version = vkeys[0] + ' – ' + vkeys[vkeys.length - 1];
            }
        }
        rows.push(['Version', version || '-']);
        rows.push(['Author', plugin.author || '-']);

        var reqWp = plugin.requires_wp || (catalog && catalog.agent && catalog.agent.requires_wp) || null;
        var reqPhp = plugin.requires_php || (catalog && catalog.agent && catalog.agent.requires_php) || null;
        if (reqWp) rows.push(['Requires WordPress', reqWp]);
        if (reqPhp) rows.push(['Requires PHP', reqPhp]);

        if (plugin.plugin_uri) {
            rows.push(['Plugin URL', '<a href="' + escapeAttr(plugin.plugin_uri) + '" target="_blank">' + escapeAttr(plugin.plugin_uri) + '</a>']);
        }
        if (plugin.wp_org && plugin.wp_org.url) {
            rows.push(['WordPress.org', '<a href="' + escapeAttr(plugin.wp_org.url) + '" target="_blank">View on WordPress.org</a>']);
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

        return html;
    }

    function buildDetailsViewHtml(catalog) {
        var html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        var fields = [
            ['Vendor', catalog.vendor],
            ['License', catalog.license],
            ['Cost', catalog.cost],
            ['Category', catalog.category],
            ['Tags', catalog.tags]
        ];

        var wp = catalog.wp_org || {};
        if (wp.active_installs != null && !wp.not_found) {
            fields.push(['Active Installs', Number(wp.active_installs).toLocaleString()]);
        }
        if (wp.rating != null && !wp.not_found) {
            fields.push(['Rating', wp.rating + '%' + (wp.num_ratings ? ' (' + Number(wp.num_ratings).toLocaleString() + ' ratings)' : '')]);
        }
        if (wp.last_updated && !wp.not_found) {
            fields.push(['Last Updated', wp.last_updated]);
        }
        if (wp.tested && !wp.not_found) {
            fields.push(['Tested Up To', wp.tested]);
        }
        if (catalog.notes) {
            fields.push(['Notes', catalog.notes]);
        }

        fields.forEach(function(row) {
            if (row[1]) {
                html += '<tr>';
                html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600; width: 140px; color: #1d2327;">' + row[0] + '</td>';
                html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; color: #50575e;">' + row[1] + '</td>';
                html += '</tr>';
            }
        });

        html += '</table>';
        return html;
    }

    function buildDetailsEditHtml(catalog) {
        var costOptions = ['', 'free', 'freemium', 'paid', 'included'];
        var costSelect = '<select id="pd-edit-cost" style="width: 100%;">';
        costOptions.forEach(function(opt) {
            var sel = (catalog.cost === opt) ? ' selected' : '';
            costSelect += '<option value="' + opt + '"' + sel + '>' + (opt || '-') + '</option>';
        });
        costSelect += '</select>';

        var html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        html += '<tr><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600; width: 140px; color: #1d2327;">Vendor</td><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;"><input type="text" id="pd-edit-vendor" value="' + escapeAttr(catalog.vendor) + '" style="width: 100%;"></td></tr>';
        html += '<tr><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600; width: 140px; color: #1d2327;">License</td><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;"><input type="text" id="pd-edit-license" value="' + escapeAttr(catalog.license) + '" style="width: 100%;"></td></tr>';
        html += '<tr><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600; width: 140px; color: #1d2327;">Cost</td><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;">' + costSelect + '</td></tr>';
        html += '<tr><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600; width: 140px; color: #1d2327;">Category</td><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;"><input type="text" id="pd-edit-category" value="' + escapeAttr(catalog.category) + '" style="width: 100%;"></td></tr>';
        html += '<tr><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600; width: 140px; color: #1d2327;">Tags</td><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;"><input type="text" id="pd-edit-tags" value="' + escapeAttr(catalog.tags) + '" style="width: 100%;"></td></tr>';
        html += '<tr><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600; width: 140px; color: #1d2327; vertical-align: top;">Notes</td><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;"><textarea id="pd-edit-notes" rows="3" style="width: 100%;">' + escapeAttr(catalog.notes) + '</textarea></td></tr>';
        html += '</table>';
        return html;
    }

    function buildLicensesViewHtml(catalog) {
        var licenses = (catalog && catalog.licenses) ? catalog.licenses : [];
        if (!licenses.length) {
            return '<div style="color: #888; padding: 20px 0; text-align: center;">No licenses</div>';
        }

        var html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        html += '<thead><tr>';
        html += '<th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Key</th>';
        html += '<th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Status</th>';
        html += '<th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Expiration</th>';
        html += '<th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Term</th>';
        html += '<th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Allowed</th>';
        html += '<th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Used</th>';
        html += '</tr></thead><tbody>';

        licenses.forEach(function(lic) {
            var s = lic.status || 'inactive';
            var sc = STATUS_COLORS[s] || STATUS_COLORS.inactive;
            var badge = '<span style="background: ' + sc.bg + '; color: ' + sc.color + '; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">' + escapeAttr(s) + '</span>';
            var allowed = (lic.allowed === 0 || lic.allowed === '0') ? 'unlimited' : escapeAttr(String(lic.allowed || '-'));

            html += '<tr>';
            html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; color: #50575e; font-family: monospace; font-size: 12px;">' + escapeAttr(lic.key || '-') + '</td>';
            html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;">' + badge + '</td>';
            html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; color: #50575e;">' + escapeAttr(lic.expiration || '-') + '</td>';
            html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; color: #50575e;">' + escapeAttr(lic.term || '-') + '</td>';
            html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; color: #50575e;">' + allowed + '</td>';
            html += '<td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; color: #50575e;">' + escapeAttr(String(lic.used || '0')) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    function buildLicenseRowHtml(lic, index) {
        lic = lic || { key: '', status: 'active', expiration: '', term: 'yearly', allowed: 0, used: 0 };

        var statusOptions = ['active', 'inactive', 'expired', 'revoked'];
        var statusSelect = '<select class="pd-lic-status" style="width: 100%;">';
        statusOptions.forEach(function(opt) {
            statusSelect += '<option value="' + opt + '"' + (lic.status === opt ? ' selected' : '') + '>' + opt + '</option>';
        });
        statusSelect += '</select>';

        var termOptions = ['monthly', 'yearly', 'lifetime', 'one-time'];
        var termSelect = '<select class="pd-lic-term" style="width: 100%;">';
        termOptions.forEach(function(opt) {
            termSelect += '<option value="' + opt + '"' + (lic.term === opt ? ' selected' : '') + '>' + opt + '</option>';
        });
        termSelect += '</select>';

        var html = '<tr class="pd-lic-row">';
        html += '<td style="padding: 4px 4px 4px 0; border-bottom: 1px solid #f0f0f1;"><input type="text" class="pd-lic-key" value="' + escapeAttr(lic.key) + '" style="width: 100%; font-family: monospace; font-size: 12px;"></td>';
        html += '<td style="padding: 4px; border-bottom: 1px solid #f0f0f1;">' + statusSelect + '</td>';
        html += '<td style="padding: 4px; border-bottom: 1px solid #f0f0f1;"><input type="date" class="pd-lic-expiration" value="' + escapeAttr(lic.expiration) + '" style="width: 100%;"></td>';
        html += '<td style="padding: 4px; border-bottom: 1px solid #f0f0f1;">' + termSelect + '</td>';
        html += '<td style="padding: 4px; border-bottom: 1px solid #f0f0f1;"><input type="number" class="pd-lic-allowed" value="' + (lic.allowed || 0) + '" min="0" style="width: 60px;"></td>';
        html += '<td style="padding: 4px; border-bottom: 1px solid #f0f0f1;"><input type="number" class="pd-lic-used" value="' + (lic.used || 0) + '" min="0" style="width: 60px;"></td>';
        html += '<td style="padding: 4px; border-bottom: 1px solid #f0f0f1; text-align: center;"><button class="pd-lic-remove" style="background: none; border: none; color: #cc1818; cursor: pointer; font-size: 16px; padding: 2px 6px;">&times;</button></td>';
        html += '</tr>';
        return html;
    }

    function buildLicensesEditHtml(catalog) {
        var licenses = (catalog && catalog.licenses) ? catalog.licenses : [];

        var html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        html += '<thead><tr>';
        html += '<th style="padding: 6px 4px 6px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Key</th>';
        html += '<th style="padding: 6px 4px; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Status</th>';
        html += '<th style="padding: 6px 4px; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Expiration</th>';
        html += '<th style="padding: 6px 4px; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Term</th>';
        html += '<th style="padding: 6px 4px; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Allowed</th>';
        html += '<th style="padding: 6px 4px; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Used</th>';
        html += '<th style="padding: 6px 4px; border-bottom: 1px solid #ccd0d4; background: none; width: 30px;"></th>';
        html += '</tr></thead><tbody id="pd-lic-tbody">';

        licenses.forEach(function(lic, i) {
            html += buildLicenseRowHtml(lic, i);
        });

        html += '</tbody></table>';
        html += '<div style="margin-top: 10px;"><button id="pd-lic-add" class="button" style="font-size: 12px;">Add</button></div>';
        return html;
    }

    function collectLicenses() {
        var licenses = [];
        $('#pd-lic-tbody .pd-lic-row').each(function() {
            var $row = $(this);
            licenses.push({
                key: $row.find('.pd-lic-key').val(),
                status: $row.find('.pd-lic-status').val(),
                expiration: $row.find('.pd-lic-expiration').val(),
                term: $row.find('.pd-lic-term').val(),
                allowed: parseInt($row.find('.pd-lic-allowed').val(), 10) || 0,
                used: parseInt($row.find('.pd-lic-used').val(), 10) || 0
            });
        });
        return licenses;
    }

    function buildSitesHtml(plugin) {
        if (!plugin.versions) return '';

        var html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        html += '<thead><tr><th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Site</th><th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Version</th></tr></thead>';
        html += '<tbody>';

        var versions = Object.keys(plugin.versions).sort().reverse();
        versions.forEach(function(version) {
            var info = plugin.versions[version];
            info.sites.forEach(function(site) {
                var updateTag = site.update_available ? '<span style="background: #fcf0e3; color: #996800; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; margin-left: 8px;">update</span>' : '';
                html += '<tr>';
                html += '<td style="padding: 10px 0; border-bottom: 1px solid #f0f0f1;"><a href="admin.php?page=watchtower-manager-site-details&site=' + encodeURIComponent(site.url) + '">' + escapeAttr(site.name) + '</a></td>';
                html += '<td style="padding: 10px 0; border-bottom: 1px solid #f0f0f1;">' + escapeAttr(version) + updateTag + '</td>';
                html += '</tr>';
            });
        });

        html += '</tbody></table>';
        return html;
    }

    function getActiveTab($body) {
        return $body.find('.pd-tab.active').data('tab');
    }

    function exitEditMode($body, $editBtn) {
        if (!state.editMode) return;
        var tab = state.editMode;
        state.editMode = false;
        $editBtn.text('Edit');
        if (tab === 'details' && state.catalog) {
            $('#pd-details-content').html(buildDetailsViewHtml(state.catalog));
        } else if (tab === 'licenses' && state.catalog) {
            $('#pd-licenses-content').html(buildLicensesViewHtml(state.catalog));
        }
    }

    function show(plugin, options) {
        var dialogId = options.dialogId;
        var $dialog = $('#' + dialogId);
        var $title = $dialog.find('.watchtower-dialog-title');
        var $body = $dialog.find('.watchtower-dialog-body');
        var $footer = $dialog.find('.watchtower-dialog-footer');

        $dialog.find('.watchtower-dialog').css('width', '700px');
        $body.css('height', '400px');

        state.slug = plugin.slug || null;
        state.catalog = null;
        state.editMode = false;

        var statusBadge = '';
        if (typeof plugin.active !== 'undefined') {
            statusBadge = plugin.active
                ? '<span style="background: #d5f3e5; color: #00a32a; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600;">Active</span>'
                : '<span style="background: #f0f0f1; color: #646970; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600;">Inactive</span>';
        }
        var updateBadge = plugin.update_available
            ? '<span style="background: #fcf0e3; color: #996800; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 6px;">Update Available</span>'
            : '';

        if (statusBadge || updateBadge) {
            $title.html('<span style="display: flex; justify-content: space-between; align-items: center; width: 100%;"><span>' + escapeAttr(plugin.name) + '</span><span>' + statusBadge + updateBadge + '</span></span>');
        } else {
            $title.text(plugin.name);
        }

        var showSites = options.showSites && plugin.versions;
        var tabs = [
            { id: 'overview', label: 'Overview' },
            { id: 'details', label: 'Details' },
            { id: 'licenses', label: 'Licenses' }
        ];
        if (showSites) {
            tabs.push({ id: 'sites', label: 'Sites' });
        }

        var html = TAB_STYLE;
        html += '<div class="pd-tabs">';
        tabs.forEach(function(tab, i) {
            html += '<button class="pd-tab' + (i === 0 ? ' active' : '') + '" data-tab="' + tab.id + '">' + tab.label + '</button>';
        });
        html += '</div>';

        html += '<div class="pd-tab-content active" data-tab="overview">';
        html += buildOverviewHtml(plugin, null);
        html += '</div>';

        html += '<div class="pd-tab-content" data-tab="details">';
        html += '<div id="pd-details-content"><div style="color: #888; padding: 20px 0; text-align: center;">Loading...</div></div>';
        html += '</div>';

        html += '<div class="pd-tab-content" data-tab="licenses">';
        html += '<div id="pd-licenses-content"><div style="color: #888; padding: 20px 0; text-align: center;">Loading...</div></div>';
        html += '</div>';

        if (showSites) {
            html += '<div class="pd-tab-content" data-tab="sites">';
            html += buildSitesHtml(plugin);
            html += '</div>';
        }

        $body.html(html);

        $footer.find('.pd-edit-btn').remove();
        var $editBtn = $('<button class="watchtower-dialog-button primary pd-edit-btn" style="display: none;">Edit</button>');
        $footer.prepend($editBtn);

        $body.off('click.pdtabs').on('click.pdtabs', '.pd-tab', function() {
            var tab = $(this).data('tab');
            $body.find('.pd-tab').removeClass('active');
            $(this).addClass('active');
            $body.find('.pd-tab-content').removeClass('active');
            $body.find('.pd-tab-content[data-tab="' + tab + '"]').addClass('active');

            if ((tab === 'details' || tab === 'licenses') && state.catalog) {
                $editBtn.show();
            } else {
                $editBtn.hide();
            }

            if (state.editMode && state.editMode !== tab) {
                exitEditMode($body, $editBtn);
            }
        });

        $body.off('click.pdlicremove').on('click.pdlicremove', '.pd-lic-remove', function() {
            $(this).closest('.pd-lic-row').remove();
        });

        $body.off('click.pdlicadd').on('click.pdlicadd', '#pd-lic-add', function() {
            $('#pd-lic-tbody').append(buildLicenseRowHtml(null, 0));
        });

        $editBtn.off('click.pdedit').on('click.pdedit', function() {
            if (!state.catalog || !state.slug) return;

            var activeTab = getActiveTab($body);

            if (!state.editMode) {
                state.editMode = activeTab;
                $(this).text('Save');
                if (activeTab === 'details') {
                    $('#pd-details-content').html(buildDetailsEditHtml(state.catalog));
                } else if (activeTab === 'licenses') {
                    $('#pd-licenses-content').html(buildLicensesEditHtml(state.catalog));
                }
            } else {
                var btn = $(this);
                if (state.editMode === 'details') {
                    $.post(options.ajaxurl, {
                        action: 'watchtower_manager_save_plugin_catalog',
                        nonce: options.nonce,
                        slug: state.slug,
                        vendor: $('#pd-edit-vendor').val(),
                        license: $('#pd-edit-license').val(),
                        cost: $('#pd-edit-cost').val(),
                        category: $('#pd-edit-category').val(),
                        tags: $('#pd-edit-tags').val(),
                        notes: $('#pd-edit-notes').val()
                    }, function(response) {
                        if (response.success && response.data.catalog) {
                            state.catalog = response.data.catalog;
                            state.editMode = false;
                            btn.text('Edit');
                            $('#pd-details-content').html(buildDetailsViewHtml(state.catalog));
                        }
                    });
                } else if (state.editMode === 'licenses') {
                    var licenses = collectLicenses();
                    $.post(options.ajaxurl, {
                        action: 'watchtower_manager_save_plugin_catalog',
                        nonce: options.nonce,
                        slug: state.slug,
                        licenses: JSON.stringify(licenses)
                    }, function(response) {
                        if (response.success && response.data.catalog) {
                            state.catalog = response.data.catalog;
                            state.editMode = false;
                            btn.text('Edit');
                            $('#pd-licenses-content').html(buildLicensesViewHtml(state.catalog));
                        }
                    });
                }
            }
        });

        $dialog.show();

        if (state.slug) {
            $.post(options.ajaxurl, {
                action: 'watchtower_manager_get_plugin_catalog',
                nonce: options.nonce,
                slug: state.slug
            }, function(response) {
                if (response.success && response.data.catalog) {
                    state.catalog = response.data.catalog;
                    $('#pd-details-content').html(buildDetailsViewHtml(state.catalog));
                    $('#pd-licenses-content').html(buildLicensesViewHtml(state.catalog));

                    var overviewContent = $body.find('.pd-tab-content[data-tab="overview"]');
                    overviewContent.html(buildOverviewHtml(plugin, state.catalog));

                    var activeTab = getActiveTab($body);
                    if (activeTab === 'details' || activeTab === 'licenses') {
                        $editBtn.show();
                    }
                } else {
                    $('#pd-details-content').html('<div style="color: #888; padding: 20px 0; text-align: center;">No catalog data</div>');
                    $('#pd-licenses-content').html('<div style="color: #888; padding: 20px 0; text-align: center;">No catalog data</div>');
                }
            });
        } else {
            $('#pd-details-content').html('<div style="color: #888; padding: 20px 0; text-align: center;">No catalog data</div>');
            $('#pd-licenses-content').html('<div style="color: #888; padding: 20px 0; text-align: center;">No catalog data</div>');
        }
    }

    return { show: show };
})(jQuery);
