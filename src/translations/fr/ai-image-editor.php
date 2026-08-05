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
 * French (fr) translations for the `ai-image-editor` category. Machine-generated
 * baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Modifier les images avec l\'AI',
    'Generate images with AI' => 'Générer des images avec l\'AI',
    'Edit with AI' => 'Modifier avec l\'AI',
    'Purging stale AI image edit sessions' => 'Purge des sessions d\'édition d\'images AI obsolètes',

    // Editor: buttons and actions
    'Save' => 'Enregistrer',
    'Save as a new asset' => 'Enregistrer comme nouvelle ressource',
    'Accept & Save' => 'Accepter et enregistrer',
    'Discard' => 'Abandonner',
    'Apply' => 'Appliquer',
    'Generate' => 'Générer',
    'Retry' => 'Réessayer',
    'Back to editing' => 'Retour à l\'édition',
    'Revert to original' => 'Revenir à l\'original',
    'Revert to this version' => 'Revenir à cette version',
    'Quick actions' => 'Actions rapides',

    // Editor: composer labels and controls
    'Precise edits' => 'Modifications précises',
    'Model' => 'Modèle',
    'Aspect ratio' => 'Format d\'image',
    'Output format' => 'Format de sortie',
    'Final resolution' => 'Résolution finale',
    'Match original' => 'Comme l\'original',
    'Auto' => 'Automatique',
    'Draft' => 'Brouillon',
    'Final' => 'Finale',
    'Original' => 'Original',
    'Turn {number}' => 'Étape {number}',
    'and' => 'et',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Décrivez la modification souhaitée…',
    'Describe the image you want to create…' => 'Décrivez l\'image à créer…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Décrivez l\'image à créer, puis appliquez pour générer le premier brouillon.',
    'Compare the draft and final versions, then choose which one to save.' => 'Comparez le brouillon et la version finale, puis choisissez celle à enregistrer.',
    'Images and prompts are sent to {provider} for processing.' => 'Les images et les prompts sont envoyés à {provider} pour traitement.',
    'Working…' => 'En cours…',
    'Generating the final version…' => 'Génération de la version finale…',
    'Edited image saved as {filename}.' => 'Image modifiée enregistrée sous {filename}.',
    'The image could not be loaded.' => 'Impossible de charger l\'image.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Abandonner cette session et toutes les modifications ?',
    'Revert to the original image? All edits will be discarded.' => 'Revenir à l\'image originale ? Toutes les modifications seront supprimées.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Revenir à cette version ? Tous les brouillons créés ensuite seront supprimés.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'Impossible de créer le pilote d\'édition configuré. Vérifiez `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} n\'est pas configuré. Ajoutez votre clé API dans `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'Cette session n\'est plus active.',
    'Please enter an instruction.' => 'Veuillez saisir une instruction.',
    'The edit could not be performed. Please try again.' => 'La modification n\'a pas pu être effectuée. Veuillez réessayer.',
    'The high resolution version could not be generated.' => 'La version haute résolution n\'a pas pu être générée.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Vous envoyez des requêtes trop rapidement. Veuillez patienter un instant et réessayer.',
    'Only image assets can be edited.' => 'Seules les ressources image peuvent être modifiées.',
    'Vector images can not be edited.' => 'Les images vectorielles ne peuvent pas être modifiées.',
    'The source image could not be read.' => 'Impossible de lire l\'image source.',
    'The source image could not be copied into the edit session.' => 'Impossible de copier l\'image source dans la session d\'édition.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'L\'image de travail de cette session n\'existe plus. Elle a peut-être été purgée, veuillez démarrer une nouvelle session.',
    'The result image could not be read back for storage.' => 'Impossible de relire l\'image résultante pour l\'enregistrement.',
    'The edit was cancelled.' => 'La modification a été annulée.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'Aucune modification à finaliser dans cette session.',
    'There is no result to save in this session.' => 'Aucun résultat à enregistrer dans cette session.',
    'The result image no longer exists.' => 'L\'image résultante n\'existe plus.',
    'The result image could not be prepared for saving.' => 'Impossible de préparer l\'image résultante pour l\'enregistrement.',
    'The target folder for this session no longer exists.' => 'Le dossier cible de cette session n\'existe plus.',
    'The source asset for this session no longer exists.' => 'La ressource source de cette session n\'existe plus.',
    'The original asset for this session no longer exists.' => 'La ressource originale de cette session n\'existe plus.',
    'The source asset\'s folder could not be resolved.' => 'Impossible de déterminer le dossier de la ressource source.',
    'The original image could not be replaced.' => 'Impossible de remplacer l\'image originale.',
    'The edited image could not be saved as an asset.' => 'Impossible d\'enregistrer l\'image modifiée en tant que ressource.',
    'A generated image has no original to replace.' => 'Une image générée n\'a pas d\'original à remplacer.',
    'The save was cancelled.' => 'L\'enregistrement a été annulé.',
];
