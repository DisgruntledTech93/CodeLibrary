(function (blocks, element, components, blockEditor, i18n) {
  'use strict';
  var el = element.createElement;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody = components.PanelBody;
  var TextControl = components.TextControl;

  blocks.registerBlockType('rcl/code-library', {
    apiVersion: 2,
    title: i18n.__('Code Library', 'reference-code-library'),
    description: i18n.__('Displays a searchable reference code library or one collection.', 'reference-code-library'),
    icon: 'editor-code',
    category: 'widgets',
    attributes: {
      collection: { type: 'string', default: '' }
    },
    edit: function (props) {
      return el(
        element.Fragment,
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: i18n.__('Library display', 'reference-code-library') },
            el(TextControl, {
              label: i18n.__('Optional collection slug', 'reference-code-library'),
              help: i18n.__('Leave blank for the complete library. Example: css-patterns', 'reference-code-library'),
              value: props.attributes.collection,
              onChange: function (value) { props.setAttributes({ collection: value }); }
            })
          )
        ),
        el(
          'div',
          { className: 'components-placeholder' },
          el('div', { className: 'components-placeholder__label' }, i18n.__('Code Library', 'reference-code-library')),
          el('div', { className: 'components-placeholder__instructions' }, props.attributes.collection ? i18n.sprintf(i18n.__('Collection: %s', 'reference-code-library'), props.attributes.collection) : i18n.__('The complete code library will render on the front end.', 'reference-code-library'))
        )
      );
    },
    save: function () { return null; }
  });
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n));
