(function ($) {
  'use strict';

  $(function () {
    var $list = $('[data-rcl-examples-list]');
    var $empty = $('[data-rcl-examples-empty]');
    var frame;
    var maxExamples = parseInt(rclEntryExamples.maxExamples, 10) || 20;

    if (!$list.length || typeof wp === 'undefined' || !wp.media) {
      return;
    }

    function renumberRows() {
      var $rows = $list.children('.rcl-example-row');

      $rows.each(function (index) {
        var $row = $(this);
        $row.attr('data-rcl-example-index', index);
        $row.find('[data-rcl-example-number]').text(index + 1);
        $row.find('[data-rcl-example-field]').each(function () {
          var field = $(this).attr('data-rcl-example-field');
          $(this).attr('name', 'rcl_examples[' + index + '][' + field + ']');
        });
        $row.find('[data-rcl-example-field="order"]').val((index + 1) * 10);
        $row.find('[data-rcl-example-up]').prop('disabled', index === 0);
        $row.find('[data-rcl-example-down]').prop('disabled', index === $rows.length - 1);
      });

      $empty.prop('hidden', $rows.length > 0);
      $list.toggleClass('is-empty', $rows.length === 0);
    }

    function createTypeSelect() {
      var $select = $('<select>', {
        class: 'widefat',
        'data-rcl-example-field': 'type'
      });

      $.each(rclEntryExamples.types || {}, function (value, label) {
        $select.append($('<option>', { value: value, text: label }));
      });

      return $select;
    }

    function addExample(attachment) {
      var imageUrl = attachment.url;
      var fullUrl = attachment.url;
      var alt = attachment.alt || '';
      var caption = attachment.caption || '';

      if (attachment.sizes) {
        if (attachment.sizes.medium) {
          imageUrl = attachment.sizes.medium.url;
        } else if (attachment.sizes.thumbnail) {
          imageUrl = attachment.sizes.thumbnail.url;
        }
      }

      var $row = $('<div>', { class: 'rcl-example-row' });
      var $preview = $('<div>', { class: 'rcl-example-row__preview' });
      var $fields = $('<div>', { class: 'rcl-example-row__fields' });
      var $actions = $('<div>', { class: 'rcl-example-row__actions' });

      $preview.append(
        $('<a>', {
          href: fullUrl,
          target: '_blank',
          rel: 'noopener noreferrer',
          'aria-label': rclEntryExamples.openFullSize
        }).append($('<img>', { src: imageUrl, alt: '' }))
      );

      $fields.append(
        $('<input>', {
          type: 'hidden',
          value: attachment.id,
          'data-rcl-example-field': 'attachment_id'
        }),
        $('<input>', {
          type: 'hidden',
          value: 10,
          'data-rcl-example-field': 'order'
        }),
        $('<p>', { class: 'rcl-example-row__heading' }).append(
          $('<strong>').append(document.createTextNode(rclEntryExamples.example + ' '), $('<span>', { 'data-rcl-example-number': true }))
        ),
        $('<label>').append(
          $('<span>', { text: rclEntryExamples.typeLabel }),
          createTypeSelect()
        ),
        $('<label>').append(
          $('<span>', { text: rclEntryExamples.altLabel }),
          $('<input>', {
            type: 'text',
            class: 'widefat',
            value: alt,
            'data-rcl-example-field': 'alt'
          }),
          $('<span>', { class: 'description', text: rclEntryExamples.altHelp })
        ),
        $('<label>').append(
          $('<span>', { text: rclEntryExamples.captionLabel }),
          $('<textarea>', {
            class: 'widefat',
            rows: 3,
            text: caption,
            'data-rcl-example-field': 'caption'
          })
        )
      );

      $actions.append(
        $('<button>', {
          type: 'button',
          class: 'button button-secondary',
          text: rclEntryExamples.moveUp,
          'data-rcl-example-up': true
        }),
        $('<button>', {
          type: 'button',
          class: 'button button-secondary',
          text: rclEntryExamples.moveDown,
          'data-rcl-example-down': true
        }),
        $('<button>', {
          type: 'button',
          class: 'button-link-delete',
          text: rclEntryExamples.remove,
          'data-rcl-example-remove': true
        })
      );

      $row.append($preview, $fields, $actions);
      $list.append($row);
      renumberRows();
    }

    $('[data-rcl-add-examples]').on('click', function (event) {
      event.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: rclEntryExamples.chooseTitle,
        button: { text: rclEntryExamples.useImages },
        library: { type: 'image' },
        multiple: true
      });

      frame.on('select', function () {
        var selection = frame.state().get('selection');
        var available = Math.max(0, maxExamples - $list.children('.rcl-example-row').length);

        selection.each(function (model, index) {
          if (index < available) {
            addExample(model.toJSON());
          }
        });

        if (selection.length > available) {
          window.alert(rclEntryExamples.maxMessage);
        }
      });

      frame.open();
    });

    $list.on('click', '[data-rcl-example-remove]', function () {
      $(this).closest('.rcl-example-row').remove();
      renumberRows();
    });

    $list.on('click', '[data-rcl-example-up]', function () {
      var $row = $(this).closest('.rcl-example-row');
      var $previous = $row.prev('.rcl-example-row');
      if ($previous.length) {
        $row.insertBefore($previous);
        renumberRows();
      }
    });

    $list.on('click', '[data-rcl-example-down]', function () {
      var $row = $(this).closest('.rcl-example-row');
      var $next = $row.next('.rcl-example-row');
      if ($next.length) {
        $row.insertAfter($next);
        renumberRows();
      }
    });

    renumberRows();
  });
}(jQuery));
