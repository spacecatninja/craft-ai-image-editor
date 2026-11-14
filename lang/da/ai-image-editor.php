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
 * Danish (da) translations for the `ai-image-editor` category. Machine-generated
 * baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Rediger billeder med AI',
    'Generate images with AI' => 'Generer billeder med AI',
    'Edit with AI' => 'Rediger med AI',
    'Purging stale AI image edit sessions' => 'Rydder forældede AI-billedredigeringssessioner op',

    // Editor: buttons and actions
    'Save' => 'Gem',
    'Save as a new asset' => 'Gem som ny fil',
    'Accept & Save' => 'Accepter og gem',
    'Discard' => 'Kassér',
    'Apply' => 'Anvend',
    'Generate' => 'Generer',
    'Retry' => 'Prøv igen',
    'Back to editing' => 'Tilbage til redigering',
    'Revert to original' => 'Gendan til original',
    'Revert to this version' => 'Gendan til denne version',
    'Quick actions' => 'Hurtighandlinger',

    // Editor: composer labels and controls
    'Precise edits' => 'Præcise redigeringer',
    'Model' => 'Model',
    'Aspect ratio' => 'Billedformat',
    'Output format' => 'Outputformat',
    'Final resolution' => 'Endelig opløsning',
    'Match original' => 'Som original',
    'Auto' => 'Automatisk',
    'Draft' => 'Udkast',
    'Final' => 'Endelig',
    'Original' => 'Original',
    'Turn {number}' => 'Trin {number}',
    'and' => 'og',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Beskriv den ændring, du vil foretage…',
    'Describe the image you want to create…' => 'Beskriv det billede, du vil oprette…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Beskriv det billede, du vil oprette, og anvend for at generere det første udkast.',
    'Compare the draft and final versions, then choose which one to save.' => 'Sammenlign udkastet og den endelige version, og vælg, hvilken du vil gemme.',
    'Images and prompts are sent to {provider} for processing.' => 'Billeder og prompts sendes til {provider} til behandling.',
    'Working…' => 'Arbejder…',
    'Generating the final version…' => 'Genererer den endelige version…',
    'Edited image saved as {filename}.' => 'Redigeret billede gemt som {filename}.',
    'The image could not be loaded.' => 'Billedet kunne ikke indlæses.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Kassér denne session og alle redigeringer?',
    'Revert to the original image? All edits will be discarded.' => 'Gendan til det originale billede? Alle redigeringer kasseres.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Gendan til denne version? Alle udkast, der er lavet efterfølgende, kasseres.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'Den konfigurerede redigeringsdriver kunne ikke oprettes. Tjek `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} er ikke konfigureret. Tilføj din API-nøgle i `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'Denne session er ikke længere aktiv.',
    'Please enter an instruction.' => 'Indtast en instruktion.',
    'The edit could not be performed. Please try again.' => 'Redigeringen kunne ikke udføres. Prøv igen.',
    'The high resolution version could not be generated.' => 'Højopløsningsversionen kunne ikke genereres.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Du sender forespørgsler for hurtigt. Vent et øjeblik, og prøv igen.',
    'Only image assets can be edited.' => 'Kun billedfiler kan redigeres.',
    'Vector images can not be edited.' => 'Vektorbilleder kan ikke redigeres.',
    'The source image could not be read.' => 'Kildebilledet kunne ikke læses.',
    'The source image could not be copied into the edit session.' => 'Kildebilledet kunne ikke kopieres til redigeringssessionen.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'Arbejdsbilledet for denne session findes ikke længere. Det kan være blevet ryddet, start en ny session.',
    'The result image could not be read back for storage.' => 'Resultatbilledet kunne ikke læses tilbage til lagring.',
    'The edit was cancelled.' => 'Redigeringen blev annulleret.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'Der er ingen redigeringer at færdiggøre i denne session.',
    'There is no result to save in this session.' => 'Der er intet resultat at gemme i denne session.',
    'The result image no longer exists.' => 'Resultatbilledet findes ikke længere.',
    'The result image could not be prepared for saving.' => 'Resultatbilledet kunne ikke forberedes til lagring.',
    'The target folder for this session no longer exists.' => 'Målmappen for denne session findes ikke længere.',
    'The source asset for this session no longer exists.' => 'Kildefilen for denne session findes ikke længere.',
    'The original asset for this session no longer exists.' => 'Den oprindelige fil for denne session findes ikke længere.',
    'The source asset\'s folder could not be resolved.' => 'Kildefilens mappe kunne ikke bestemmes.',
    'The original image could not be replaced.' => 'Det originale billede kunne ikke erstattes.',
    'The edited image could not be saved as an asset.' => 'Det redigerede billede kunne ikke gemmes som en fil.',
    'A generated image has no original to replace.' => 'Et genereret billede har ingen original at erstatte.',
    'The save was cancelled.' => 'Lagringen blev annulleret.',
];
