// global functions
var determine_plugins_themes_to_migrate;
var migrate_plugins_themes_recursive;

(function($) {
  var remote_plugins = {};
  var remote_themes = {};
  var remote_lists_loaded = false;

  function migration_type() {
    return $('input[name=action]:checked').val();
  }

  function get_source_target() {
    var intent = migration_type();
    if (intent === 'pull') {
      return { source: 'remote', target: 'local' };
    }
    if (intent === 'push') {
      return { source: 'local', target: 'remote' };
    }
    return { source: 'local', target: 'remote' };
  }

  function normalize_version(version) {
    if (typeof version === 'undefined' || version === null) {
      return '';
    }
    return ('' + version).trim();
  }

  function version_compare(a, b) {
    a = normalize_version(a);
    b = normalize_version(b);

    if (a === b) {
      return 0;
    }

    var pa = a.split(/[^0-9A-Za-z]+/);
    var pb = b.split(/[^0-9A-Za-z]+/);
    var len = Math.max(pa.length, pb.length);

    for (var i = 0; i < len; i++) {
      var va = pa[i] || '';
      var vb = pb[i] || '';

      if (va === vb) {
        continue;
      }

      var na = va.match(/^\d+$/) ? parseInt(va, 10) : va.toLowerCase();
      var nb = vb.match(/^\d+$/) ? parseInt(vb, 10) : vb.toLowerCase();

      if (typeof na === 'number' && typeof nb === 'number') {
        if (na > nb) return 1;
        if (na < nb) return -1;
      } else {
        if ('' + na > '' + nb) return 1;
        if ('' + na < '' + nb) return -1;
      }
    }

    return 0;
  }

  function status_for_versions(source_exists, target_exists, source_version, target_version) {
    if (!source_exists) {
      return { label: 'Missing on source', klass: 'pt-status-missing-source' };
    }

    if (!target_exists) {
      return { label: 'Missing on target', klass: 'pt-status-missing-target' };
    }

    var compare = version_compare(source_version, target_version);
    if (compare > 0) {
      return { label: 'Upgrade', klass: 'pt-status-upgrade' };
    }

    if (compare < 0) {
      return { label: 'Downgrade', klass: 'pt-status-downgrade' };
    }

    return { label: 'Same version', klass: 'pt-status-same' };
  }

  function get_selected_map(scope) {
    var selected = [];
    if (scope === 'plugins' && typeof wpsdb_loaded_plugins !== 'undefined') {
      selected = wpsdb_loaded_plugins;
    }
    if (scope === 'themes' && typeof wpsdb_loaded_themes !== 'undefined') {
      selected = wpsdb_loaded_themes;
    }

    var map = {};
    if ($.isArray(selected)) {
      $.each(selected, function(i, value) {
        map[value] = true;
      });
    }
    return map;
  }

  function get_selected_items(scope) {
    var selected = [];
    $('input[name="selected_' + scope + '[]"]:checked').each(function() {
      selected.push($(this).val());
    });
    return selected;
  }

  function get_union_keys(local_map, remote_map) {
    var keys = {};
    $.each(local_map, function(key) { keys[key] = true; });
    $.each(remote_map, function(key) { keys[key] = true; });
    return Object.keys(keys);
  }

  function render_table(scope, local_map, remote_map) {
    var source_target = get_source_target();
    var selected = get_selected_map(scope);
    var table = scope === 'plugins' ? $('.plugins-table') : $('.themes-table');
    var tbody = $('tbody', table);
    tbody.empty();

    var source_map = source_target.source === 'local' ? local_map : remote_map;
    var target_map = source_target.target === 'local' ? local_map : remote_map;

    var keys = get_union_keys(local_map, remote_map);
    keys.sort(function(a, b) {
      var name_a = (local_map[a] && local_map[a].name) || (remote_map[a] && remote_map[a].name) || a;
      var name_b = (local_map[b] && local_map[b].name) || (remote_map[b] && remote_map[b].name) || b;
      name_a = name_a.toLowerCase();
      name_b = name_b.toLowerCase();
      if (name_a < name_b) return -1;
      if (name_a > name_b) return 1;
      return 0;
    });

    $.each(keys, function(i, key) {
      var local_item = local_map[key];
      var remote_item = remote_map[key];
      var source_item = source_map[key];
      var target_item = target_map[key];

      var source_exists = typeof source_item !== 'undefined';
      var target_exists = typeof target_item !== 'undefined';
      var source_version = source_item ? source_item.version : '';
      var target_version = target_item ? target_item.version : '';

      var status = status_for_versions(source_exists, target_exists, source_version, target_version);
      var name = (local_item && local_item.name) || (remote_item && remote_item.name) || key;

      var checkbox = '<input type="checkbox" name="selected_' + scope + '[]" value="' + key + '"';
      if (selected[key]) {
        checkbox += ' checked="checked"';
      }
      if (!source_exists) {
        checkbox += ' disabled="disabled"';
      }
      checkbox += ' />';

      var row = $('<tr></tr>');
      row.append('<td class="pt-col-select">' + checkbox + '</td>');
      row.append('<td class="pt-name">' + name + '<div class="pt-slug">' + key + '</div></td>');
      row.append('<td>' + (local_item ? normalize_version(local_item.version) : '—') + '</td>');
      row.append('<td>' + (remote_item ? normalize_version(remote_item.version) : '—') + '</td>');
      row.append('<td class="pt-status"><span class="pt-status-badge ' + status.klass + '">' + status.label + '</span></td>');

      tbody.append(row);
    });

    if (!keys.length) {
      tbody.append('<tr><td colspan="5" class="pt-empty">No items found.</td></tr>');
    }
  }

  function update_source_target_labels() {
    var source_target = get_source_target();
    var source_label = source_target.source === 'local' ? 'Local' : 'Remote';
    var target_label = source_target.target === 'local' ? 'Local' : 'Remote';
    $('.plugins-themes-options .pt-source-label').text(source_label);
    $('.plugins-themes-options .pt-target-label').text(target_label);
  }

  function render_all() {
    if (migration_type() === 'savefile') {
      $('.plugins-themes-options').hide();
      return;
    }

    $('.plugins-themes-options').show();

    var local_plugins = (typeof wpsdb_local_plugins !== 'undefined') ? wpsdb_local_plugins : {};
    var local_themes = (typeof wpsdb_local_themes !== 'undefined') ? wpsdb_local_themes : {};
    var has_remote = remote_lists_loaded;

    update_source_target_labels();

    if (!has_remote) {
      $('.plugins-themes-hint').text(wpsdbpt_strings.no_connection).show();
      return;
    }

    $('.plugins-themes-hint').hide();
    render_table('plugins', local_plugins, remote_plugins);
    render_table('themes', local_themes, remote_themes);
  }

  function fetch_remote_lists() {
    if (migration_type() === 'savefile') {
      return;
    }

    var connection_info = $.trim($('.pull-push-connection-info').val()).split("\n");
    if (!connection_info || connection_info.length < 2) {
      return;
    }

    $('.plugins-themes-hint').text(wpsdbpt_strings.loading_lists).show();
    remote_lists_loaded = false;

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      dataType: 'json',
      cache: false,
      data: {
        action: 'wpsdbpt_get_lists',
        url: connection_info[0],
        key: connection_info[1],
        intent: migration_type(),
        nonce: wpsdb_nonces.get_plugins_themes_lists
      },
      error: function() {
        $('.plugins-themes-hint').text(wpsdbpt_strings.lists_failed).show();
        remote_lists_loaded = false;
      },
      success: function(data) {
        if (typeof data.wpsdb_error !== 'undefined' && data.wpsdb_error == 1) {
          $('.plugins-themes-hint').text(wpsdbpt_strings.lists_failed).show();
          remote_lists_loaded = false;
          return;
        }

        remote_plugins = (data && data.plugins) ? data.plugins : {};
        remote_themes = (data && data.themes) ? data.themes : {};
        remote_lists_loaded = true;
        render_all();
      }
    });
  }

  function should_migrate_plugins_themes() {
    if (migration_type() === 'savefile') {
      return false;
    }

    return get_selected_items('plugins').length > 0 ||
      get_selected_items('themes').length > 0;
  }

  function handle_selection_controls() {
    $('body').delegate('.pt-select-all', 'click', function() {
      var scope = $(this).data('scope');
      $('input[name="selected_' + scope + '[]"]:enabled').prop('checked', true);
    });

    $('body').delegate('.pt-deselect-all', 'click', function() {
      var scope = $(this).data('scope');
      $('input[name="selected_' + scope + '[]"]:enabled').prop('checked', false);
    });

    $('body').delegate('.pt-invert-selection', 'click', function() {
      var scope = $(this).data('scope');
      $('input[name="selected_' + scope + '[]"]:enabled').each(function() {
        $(this).prop('checked', !$(this).is(':checked'));
      });
    });
  }

  $(document).ready(function() {
    if (typeof Object.size !== 'function') {
      Object.size = function(obj) {
        var size = 0;
        var key;
        for (key in obj) {
          if (obj.hasOwnProperty(key)) {
            size++;
          }
        }
        return size;
      };
    }

    handle_selection_controls();
    update_source_target_labels();

    $.wpsdb.add_action('verify_connection_to_remote_site', function() {
      fetch_remote_lists();
    });

    $.wpsdb.add_action('move_connection_info_box', function() {
      update_source_target_labels();
      render_all();
    });

    $.wpsdb.add_filter('wpsdb_before_migration_complete_hooks', function(hooks) {
      if (!should_migrate_plugins_themes()) {
        return hooks;
      }
      hooks.push('determine_plugins_themes_to_migrate');
      return hooks;
    });

    determine_plugins_themes_to_migrate = function() {
      var connection_info = $.trim($('.pull-push-connection-info').val()).split("\n");
      var selected_plugins = get_selected_items('plugins');
      var selected_themes = get_selected_items('themes');

      $('.progress-text').html(wpsdbpt_strings.determining);

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        cache: false,
        data: {
          action: 'wpsdbpt_determine_files_to_migrate',
          intent: migration_type(),
          url: connection_info[0],
          key: connection_info[1],
          selected_plugins: JSON.stringify(selected_plugins),
          selected_themes: JSON.stringify(selected_themes),
          nonce: wpsdb_nonces.determine_plugins_themes
        },
        error: function() {
          $('.progress-title').html(wpsdbpt_strings.migration_failed);
          $('.progress-text').addClass('migration-error');
          migration_error = true;
          migration_complete_events();
        },
        success: function(data) {
          if (typeof data.wpsdb_error !== 'undefined' && data.wpsdb_error == 1) {
            $('.progress-title').html(wpsdbpt_strings.migration_failed);
            $('.progress-text').html(data.body).addClass('migration-error');
            migration_error = true;
            migration_complete_events();
            return;
          }

          if (!data || !data.files || Object.size(data.files) === 0) {
            wpsdb_call_next_hook();
            return;
          }

          var args = {};
          args.files_to_migrate = data.files;
          args.total_size = parseInt(data.total_size, 10) || 0;
          args.progress_size = 0;
          args.progress_count = 0;
          args.total_files = Object.size(args.files_to_migrate);
          args.bottleneck = parseInt(wpsdb_max_request, 10) || 0;
          args.transfer_id = 'pt_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);

          $('.progress-bar').width('0px');
          $('.progress-tables').empty();
          $('.progress-tables-hover-boxes').empty();

          $('.progress-tables').prepend('<div title="Plugins & Themes" style="width: 100%;" class="progress-chunk plugins_themes_files"><span>Plugins & Themes (<span class="plugins-themes-current-file">0</span> / ' +
            wpsdb_add_commas(args.total_files) + ')</span></div>');

          next_step_in_migration = {
            fn: migrate_plugins_themes_recursive,
            args: [args]
          };
          execute_next_step();
        }
      });
    };

    migrate_plugins_themes_recursive = function(args) {
      if (Object.size(args.files_to_migrate) === 0) {
        wpsdb_call_next_hook();
        return;
      }

      var intent = migration_type();
      var file_chunk = [];
      var file_chunk_size = 0;
      var file_chunk_payload_size = 0;
      var file_chunk_count = 0;
      var max_payload_size = args.bottleneck && args.bottleneck > 0 ? Math.floor(args.bottleneck * 0.7) : 0;
      if (!max_payload_size || max_payload_size < 32768) {
        max_payload_size = 262144;
      }
      if (max_payload_size > 524288) {
        max_payload_size = 524288;
      }

      $.each(args.files_to_migrate, function(path, size) {
        size = parseInt(size, 10) || 0;
        var estimated_item_payload_size = path.length + 64;
        if (intent === 'push') {
          estimated_item_payload_size += Math.ceil(size * 1.38) + 256;
        }
        if (!file_chunk.length) {
          file_chunk.push(path);
          file_chunk_size += size;
          file_chunk_payload_size += estimated_item_payload_size;
          delete args.files_to_migrate[path];
          file_chunk_count++;
        } else {
          if ((file_chunk_payload_size + estimated_item_payload_size) > max_payload_size) {
            return false;
          }
          if (args.bottleneck && (file_chunk_size + size) > args.bottleneck) {
            return false;
          }
          file_chunk.push(path);
          file_chunk_size += size;
          file_chunk_payload_size += estimated_item_payload_size;
          delete args.files_to_migrate[path];
          file_chunk_count++;
        }
      });
      var is_last_chunk = Object.size(args.files_to_migrate) === 0;

      var connection_info = $.trim($('.pull-push-connection-info').val()).split("\n");

      $('.progress-text').html(wpsdbpt_strings.migrating);

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        cache: false,
        data: {
          action: 'wpsdbpt_migrate_files',
          intent: intent,
          url: connection_info[0],
          key: connection_info[1],
          file_chunk: file_chunk,
          transfer_id: args.transfer_id,
          is_last_chunk: is_last_chunk ? '1' : '0',
          nonce: wpsdb_nonces.migrate_plugins_themes
        },
        error: function() {
          $('.progress-title').html(wpsdbpt_strings.migration_failed);
          $('.progress-text').addClass('migration-error');
          migration_error = true;
          migration_complete_events();
        },
        success: function(data) {
          if (typeof data.wpsdb_error !== 'undefined' && data.wpsdb_error == 1) {
            $('.progress-title').html(wpsdbpt_strings.migration_failed);
            $('.progress-text').html(data.body).addClass('migration-error');
            migration_error = true;
            migration_complete_events();
            return;
          }

          var size_done = parseInt(data.size, 10);
          if (!size_done) {
            size_done = file_chunk_size;
          }
          var count_done = parseInt(data.count, 10);
          if (!count_done) {
            count_done = file_chunk_count;
          }

          args.progress_size += size_done;
          args.progress_count += count_done;

          var percent = 0;
          if (args.total_size > 0) {
            percent = Math.min(100, Math.round((args.progress_size / args.total_size) * 100));
          } else {
            percent = Math.min(100, Math.round((args.progress_count / args.total_files) * 100));
          }

          $('.progress-bar').width(percent + '%');
          $('.plugins-themes-current-file').html(wpsdb_add_commas(args.progress_count));

          next_step_in_migration = {
            fn: migrate_plugins_themes_recursive,
            args: [args]
          };
          execute_next_step();
        }
      });
    };

    render_all();
  });
})(jQuery);
