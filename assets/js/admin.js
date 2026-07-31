(function ($) {
  'use strict';

  $(function () {
    var frame;
    var $logoId = $('#rcl-logo-id');
    var $preview = $('[data-rcl-logo-preview]');
    var $legacyLogoUrl = $('#rcl-legacy-logo-url');
    var $remove = $('[data-rcl-remove-logo]');

    $('[data-rcl-choose-logo]').on('click', function (event) {
      event.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: rclAdmin.chooseLogo,
        button: { text: rclAdmin.useLogo },
        library: { type: 'image' },
        multiple: false
      });

      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
        $logoId.val(attachment.id);
        $legacyLogoUrl.val('');
        $preview.html($('<img>', { src: url, alt: '' }));
        $remove.prop('hidden', false);
      });

      frame.open();
    });

    $remove.on('click', function (event) {
      event.preventDefault();
      $logoId.val('0');
      $legacyLogoUrl.val('');
      $preview.html($('<span>').text('No logo selected'));
      $remove.prop('hidden', true);
    });

    $('[data-rcl-check-all]').on('change', function () {
      $('.rcl-entry-checklist input[name="rcl_export_entries[]"]').prop('checked', this.checked);
    });

    function updateTypographyControls() {
      var mode = $('[data-rcl-typography-mode]').val();
      $('[data-rcl-font-row]').toggle(mode === 'custom');

      $('[data-rcl-font-preset]').each(function () {
        var group = $(this).data('rcl-font-preset');
        $('[data-rcl-custom-font="' + group + '"]').toggle(mode === 'custom' && $(this).val() === 'custom');
      });
    }

    $('[data-rcl-typography-mode], [data-rcl-font-preset]').on('change', updateTypographyControls);
    updateTypographyControls();
  });
}(jQuery));
