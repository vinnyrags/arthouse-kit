import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from '../block.json';
import '../style.scss';
import './editor.scss';

// Dynamic block — markup is owned by render.php / newsletter.twig. The
// editor uses ServerSideRender to mirror that markup, so save() returns
// null (no static save markup, server is the source of truth).
registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: () => null,
});
