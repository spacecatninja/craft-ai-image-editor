<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

/**
 * Spanish (es) translations for the `ai-image-editor` category. Machine-generated
 * baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Editar imágenes con AI',
    'Generate images with AI' => 'Generar imágenes con AI',
    'Edit with AI' => 'Editar con AI',
    'Purging stale AI image edit sessions' => 'Depurando sesiones de edición de imágenes con AI obsoletas',

    // Editor: buttons and actions
    'Save' => 'Guardar',
    'Save as a new asset' => 'Guardar como nuevo activo',
    'Accept & Save' => 'Aceptar y guardar',
    'Discard' => 'Descartar',
    'Apply' => 'Aplicar',
    'Generate' => 'Generar',
    'Retry' => 'Reintentar',
    'Back to editing' => 'Volver a la edición',
    'Revert to original' => 'Revertir al original',
    'Revert to this version' => 'Revertir a esta versión',
    'Quick actions' => 'Acciones rápidas',

    // Editor: composer labels and controls
    'Precise edits' => 'Ediciones precisas',
    'Model' => 'Modelo',
    'Aspect ratio' => 'Relación de aspecto',
    'Output format' => 'Formato de salida',
    'Final resolution' => 'Resolución final',
    'Match original' => 'Como el original',
    'Auto' => 'Automático',
    'Draft' => 'Borrador',
    'Final' => 'Final',
    'Original' => 'Original',
    'Turn {number}' => 'Paso {number}',
    'and' => 'y',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Describe el cambio que quieres hacer…',
    'Describe the image you want to create…' => 'Describe la imagen que quieres crear…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Describe la imagen que quieres crear y aplica para generar el primer borrador.',
    'Compare the draft and final versions, then choose which one to save.' => 'Compara el borrador y la versión final, y elige cuál guardar.',
    'Images and prompts are sent to {provider} for processing.' => 'Las imágenes y los prompts se envían a {provider} para su procesamiento.',
    'Working…' => 'Procesando…',
    'Generating the final version…' => 'Generando la versión final…',
    'Edited image saved as {filename}.' => 'Imagen editada guardada como {filename}.',
    'The image could not be loaded.' => 'No se pudo cargar la imagen.',

    // Editor: confirmations
    'Discard this session and all edits?' => '¿Descartar esta sesión y todas las ediciones?',
    'Revert to the original image? All edits will be discarded.' => '¿Revertir a la imagen original? Se descartarán todas las ediciones.',
    'Revert to this version? Any drafts made after it will be discarded.' => '¿Revertir a esta versión? Se descartarán todos los borradores creados después.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'No se pudo crear el controlador de edición configurado. Comprueba `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} no está configurado. Añade tu clave de API en `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'Esta sesión ya no está activa.',
    'Please enter an instruction.' => 'Introduce una instrucción.',
    'The edit could not be performed. Please try again.' => 'No se pudo realizar la edición. Inténtalo de nuevo.',
    'The high resolution version could not be generated.' => 'No se pudo generar la versión de alta resolución.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Estás haciendo solicitudes demasiado rápido. Espera un momento e inténtalo de nuevo.',
    'Only image assets can be edited.' => 'Solo se pueden editar activos de imagen.',
    'Vector images can not be edited.' => 'Las imágenes vectoriales no se pueden editar.',
    'The source image could not be read.' => 'No se pudo leer la imagen de origen.',
    'The source image could not be copied into the edit session.' => 'No se pudo copiar la imagen de origen en la sesión de edición.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'La imagen de trabajo de esta sesión ya no existe. Es posible que se haya depurado, inicia una nueva sesión.',
    'The result image could not be read back for storage.' => 'No se pudo releer la imagen resultante para su almacenamiento.',
    'The edit was cancelled.' => 'La edición se canceló.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'No hay ediciones que finalizar en esta sesión.',
    'There is no result to save in this session.' => 'No hay ningún resultado que guardar en esta sesión.',
    'The result image no longer exists.' => 'La imagen resultante ya no existe.',
    'The result image could not be prepared for saving.' => 'No se pudo preparar la imagen resultante para guardarla.',
    'The target folder for this session no longer exists.' => 'La carpeta de destino de esta sesión ya no existe.',
    'The source asset for this session no longer exists.' => 'El activo de origen de esta sesión ya no existe.',
    'The original asset for this session no longer exists.' => 'El activo original de esta sesión ya no existe.',
    'The source asset\'s folder could not be resolved.' => 'No se pudo determinar la carpeta del activo de origen.',
    'The original image could not be replaced.' => 'No se pudo reemplazar la imagen original.',
    'The edited image could not be saved as an asset.' => 'No se pudo guardar la imagen editada como activo.',
    'A generated image has no original to replace.' => 'Una imagen generada no tiene ningún original que reemplazar.',
    'The save was cancelled.' => 'El guardado se canceló.',
];
