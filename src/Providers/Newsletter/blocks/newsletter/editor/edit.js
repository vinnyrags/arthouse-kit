import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Disabled, PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

// ServerSideRender hits render.php and embeds the same markup the
// frontend will emit, giving authors true editor parity. The rendered
// output is wrapped in <Disabled> so the form's submit handler doesn't
// fire in the editor canvas — but the block wrapper stays selectable so
// authors can click anywhere on the form to focus the block.
export default function Edit({ attributes, setAttributes }) {
    const { placeholder, submitLabel, requiredLabel } = attributes;

    const blockProps = useBlockProps();

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Form Labels', 'arthouse-kit')} initialOpen={true}>
                    <TextControl
                        label={__('Email Placeholder', 'arthouse-kit')}
                        value={placeholder}
                        onChange={(value) => setAttributes({ placeholder: value })}
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Submit Button Label', 'arthouse-kit')}
                        value={submitLabel}
                        onChange={(value) => setAttributes({ submitLabel: value })}
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Required Note', 'arthouse-kit')}
                        help={__('Small text shown below the form. Leave blank to hide.', 'arthouse-kit')}
                        value={requiredLabel}
                        onChange={(value) => setAttributes({ requiredLabel: value })}
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <Disabled>
                    <ServerSideRender
                        block="arthouse/newsletter"
                        attributes={attributes}
                    />
                </Disabled>
            </div>
        </>
    );
}
