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
 * Portuguese (pt) translations for the `ai-image-editor` category. Machine-generated
 * baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Editar imagens com AI',
    'Generate images with AI' => 'Gerar imagens com AI',
    'Edit with AI' => 'Editar com AI',
    'Purging stale AI image edit sessions' => 'A limpar sessões de edição de imagens com AI obsoletas',

    // Editor: buttons and actions
    'Save' => 'Guardar',
    'Save as a new asset' => 'Guardar como novo recurso',
    'Accept & Save' => 'Aceitar e guardar',
    'Discard' => 'Descartar',
    'Apply' => 'Aplicar',
    'Generate' => 'Gerar',
    'Retry' => 'Tentar novamente',
    'Back to editing' => 'Voltar à edição',
    'Revert to original' => 'Reverter para o original',
    'Revert to this version' => 'Reverter para esta versão',
    'Quick actions' => 'Ações rápidas',

    // Editor: composer labels and controls
    'Precise edits' => 'Edições precisas',
    'Model' => 'Modelo',
    'Aspect ratio' => 'Proporção',
    'Output format' => 'Formato de saída',
    'Final resolution' => 'Resolução final',
    'Match original' => 'Como o original',
    'Auto' => 'Automático',
    'Draft' => 'Rascunho',
    'Final' => 'Final',
    'Original' => 'Original',
    'Turn {number}' => 'Passo {number}',
    'and' => 'e',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Descreva a alteração que pretende fazer…',
    'Describe the image you want to create…' => 'Descreva a imagem que pretende criar…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Descreva a imagem que pretende criar e aplique para gerar o primeiro rascunho.',
    'Compare the draft and final versions, then choose which one to save.' => 'Compare o rascunho e a versão final e escolha qual guardar.',
    'Images and prompts are sent to {provider} for processing.' => 'As imagens e os prompts são enviados para {provider} para processamento.',
    'Working…' => 'A processar…',
    'Generating the final version…' => 'A gerar a versão final…',
    'Edited image saved as {filename}.' => 'Imagem editada guardada como {filename}.',
    'The image could not be loaded.' => 'Não foi possível carregar a imagem.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Descartar esta sessão e todas as edições?',
    'Revert to the original image? All edits will be discarded.' => 'Reverter para a imagem original? Todas as edições serão descartadas.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Reverter para esta versão? Todos os rascunhos criados posteriormente serão descartados.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'Não foi possível criar o controlador de edição configurado. Verifique `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} não está configurado. Adicione a sua chave de API em `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'Esta sessão já não está ativa.',
    'Please enter an instruction.' => 'Introduza uma instrução.',
    'The edit could not be performed. Please try again.' => 'Não foi possível efetuar a edição. Tente novamente.',
    'The high resolution version could not be generated.' => 'Não foi possível gerar a versão em alta resolução.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Está a fazer pedidos demasiado depressa. Aguarde um momento e tente novamente.',
    'Only image assets can be edited.' => 'Apenas recursos de imagem podem ser editados.',
    'Vector images can not be edited.' => 'As imagens vetoriais não podem ser editadas.',
    'The source image could not be read.' => 'Não foi possível ler a imagem de origem.',
    'The source image could not be copied into the edit session.' => 'Não foi possível copiar a imagem de origem para a sessão de edição.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'A imagem de trabalho desta sessão já não existe. Pode ter sido removida, inicie uma nova sessão.',
    'The result image could not be read back for storage.' => 'Não foi possível reler a imagem de resultado para armazenamento.',
    'The edit was cancelled.' => 'A edição foi cancelada.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'Não há edições para finalizar nesta sessão.',
    'There is no result to save in this session.' => 'Não há nenhum resultado para guardar nesta sessão.',
    'The result image no longer exists.' => 'A imagem de resultado já não existe.',
    'The result image could not be prepared for saving.' => 'Não foi possível preparar a imagem de resultado para guardar.',
    'The target folder for this session no longer exists.' => 'A pasta de destino desta sessão já não existe.',
    'The source asset for this session no longer exists.' => 'O recurso de origem desta sessão já não existe.',
    'The original asset for this session no longer exists.' => 'O recurso original desta sessão já não existe.',
    'The source asset\'s folder could not be resolved.' => 'Não foi possível determinar a pasta do recurso de origem.',
    'The original image could not be replaced.' => 'Não foi possível substituir a imagem original.',
    'The edited image could not be saved as an asset.' => 'Não foi possível guardar a imagem editada como recurso.',
    'A generated image has no original to replace.' => 'Uma imagem gerada não tem um original para substituir.',
    'The save was cancelled.' => 'A gravação foi cancelada.',
];
