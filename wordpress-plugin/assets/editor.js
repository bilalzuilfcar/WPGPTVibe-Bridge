(function (wp) {
    'use strict';
    const el = wp.element.createElement;
    const { RichText, InnerBlocks, InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps, useInnerBlocksProps } = wp.blockEditor;
    const { PanelBody, RangeControl, TextControl, Button, SelectControl, ToggleControl, Notice } = wp.components;
    const names = ['section', 'text', 'image', 'button', 'columns', 'card'];
    names.forEach(function (kind) {
        const container = ['section', 'columns', 'card'].includes(kind);
        wp.blocks.registerBlockType('wpgptvibe/' + kind, {
            apiVersion: 3,
            title: 'WPGPTVibe ' + kind.charAt(0).toUpperCase() + kind.slice(1),
            icon: kind === 'image' ? 'format-image' : 'layout', category: 'design',
            attributes: WPGPTVibeEditor.schema,
            supports: { html: false, align: ['wide', 'full'] },
            edit: function (props) {
                const a = props.attributes;
                const locked = wp.data.useSelect(function (select) { return !!select('core/editor').getEditedPostAttribute('meta')?._wpgptvibe_content_only; }, []);
                const style = { padding: a.padding + 'px', background: a.background || undefined, color: a.color || undefined, textAlign: ['left','center','right'].includes(a.align) ? a.align : undefined };
                const blockProps = useBlockProps({ className: 'wpgptvibe-' + kind, style: style });
                let controls = [];
                if (!locked) controls.push(el(RangeControl, { label: 'Section spacing', value: a.padding, min: 0, max: 160, onChange: v => props.setAttributes({ padding: v }) }));
                if (kind === 'columns' && !locked) controls.push(el(RangeControl, { label: 'Columns on desktop', value: a.columns, min: 1, max: 4, onChange: v => props.setAttributes({ columns: v }) }));
                if (!locked) controls.push(el(SelectControl, { label: 'Text alignment', value: a.align, options: [{label:'Default',value:''},{label:'Left',value:'left'},{label:'Centre',value:'center'},{label:'Right',value:'right'}], onChange: v => props.setAttributes({ align: v }) }));
                if (!locked) controls.push(el(TextControl, { label: 'Background colour (hex)', value: a.background, onChange: v => props.setAttributes({ background: v }) }));
                const innerProps = container ? useInnerBlocksProps({ ...blockProps, style: { ...style, '--wpgptvibe-columns': a.columns } }, { templateLock: locked ? 'contentOnly' : false }) : null;
                const inspector = el(InspectorControls, {}, el(PanelBody, { title: 'Section settings' }, ...controls));
                let content = [];
                if (kind === 'image') {
                    content.push(el(MediaUploadCheck, {}, el(MediaUpload, { allowedTypes: ['image'], value: a.imageId, onSelect: media => props.setAttributes({ imageId: media.id, imageUrl: media.url, alt: media.alt || '' }), render: obj => el(Button, { variant: 'secondary', onClick: obj.open }, a.imageId ? 'Replace image' : 'Choose image') })));
                    if (a.imageUrl) content.push(el('img', { src: a.imageUrl, alt: a.alt, style: {maxWidth:'100%'} }));
                    content.push(el(TextControl, { label: 'Image description (alt text)', value: a.alt, onChange: v => props.setAttributes({alt:v}) }));
                } else if (kind === 'button') {
                    content.push(el(RichText, { tagName: 'span', className: 'wp-element-button', value: a.label, placeholder: 'Button text', onChange: v => props.setAttributes({ label: v }) }));
                    content.push(el(TextControl, { label: 'Button link', value: a.url, onChange: v => props.setAttributes({ url: v }) }));
                } else if (kind !== 'columns' && kind !== 'section') {
                    content.push(el(RichText, { tagName: 'h' + a.level, value: a.heading, placeholder: 'Heading', onChange: v => props.setAttributes({ heading: v }) }));
                    content.push(el(RichText, { tagName: 'div', multiline: 'p', value: a.body, placeholder: 'Write your content here', onChange: v => props.setAttributes({ body: v }) }));
                }
                if (container) content.push(innerProps.children);
                return el(wp.element.Fragment, {}, inspector, el('div', innerProps || blockProps, ...content));
            },
            save: container ? () => el(InnerBlocks.Content) : () => null
        });
    });
    wp.plugins.registerPlugin('wpgptvibe-editing', {
        render: function () {
            const { meta, id } = wp.data.useSelect(select => ({meta: select('core/editor').getEditedPostAttribute('meta') || {}, id: select('core/editor').getCurrentPostId()}), []);
            const editPost = wp.data.useDispatch('core/editor').editPost;
            return el(wp.editPost.PluginDocumentSettingPanel, { name: 'wpgptvibe-page', title: 'WPGPTVibe editing' },
                el('p', {}, 'Edit text and images directly. Use the block toolbar or List View to move and duplicate sections.'),
                WPGPTVibeEditor.canUnlock ? el(ToggleControl, {label:'Protect section layouts', help:'Keep content editable while protecting supported section layouts.', checked:!!meta._wpgptvibe_content_only, onChange: value => editPost({meta:{...meta,_wpgptvibe_content_only:value}})}) : null,
                el('p', {}, 'Find editable starter sections in the block inserter under Patterns → WPGPTVibe editable sections.'),
                id ? el('p', {}, 'Save changes before asking ChatGPT to modify this page.') : null
            );
        }
    });
})(window.wp);
