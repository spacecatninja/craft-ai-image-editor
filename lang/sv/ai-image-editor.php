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
 * Swedish (sv) translations for the `ai-image-editor` category. Machine-generated
 * baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Redigera bilder med AI',
    'Generate images with AI' => 'Generera bilder med AI',
    'Edit with AI' => 'Redigera med AI',
    'Purging stale AI image edit sessions' => 'Rensar inaktuella AI-bildredigeringssessioner',

    // Editor: buttons and actions
    'Save' => 'Spara',
    'Save as a new asset' => 'Spara som ny tillgång',
    'Accept & Save' => 'Acceptera och spara',
    'Discard' => 'Släng',
    'Apply' => 'Använd',
    'Generate' => 'Generera',
    'Retry' => 'Försök igen',
    'Back to editing' => 'Tillbaka till redigering',
    'Revert to original' => 'Återställ till original',
    'Revert to this version' => 'Återställ till denna version',
    'Quick actions' => 'Snabbåtgärder',

    // Editor: composer labels and controls
    'Precise edits' => 'Precisa redigeringar',
    'Model' => 'Modell',
    'Aspect ratio' => 'Bildförhållande',
    'Output format' => 'Utdataformat',
    'Final resolution' => 'Slutlig upplösning',
    'Match original' => 'Som original',
    'Auto' => 'Automatisk',
    'Draft' => 'Utkast',
    'Final' => 'Slutlig',
    'Original' => 'Original',
    'Turn {number}' => 'Steg {number}',
    'and' => 'och',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Beskriv ändringen du vill göra…',
    'Describe the image you want to create…' => 'Beskriv bilden du vill skapa…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Beskriv bilden du vill skapa och använd för att generera det första utkastet.',
    'Compare the draft and final versions, then choose which one to save.' => 'Jämför utkastet och den slutliga versionen och välj vilken du vill spara.',
    'Images and prompts are sent to {provider} for processing.' => 'Bilder och prompter skickas till {provider} för bearbetning.',
    'Working…' => 'Arbetar…',
    'Generating the final version…' => 'Genererar den slutliga versionen…',
    'Edited image saved as {filename}.' => 'Redigerad bild sparad som {filename}.',
    'The image could not be loaded.' => 'Bilden kunde inte laddas.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Släng den här sessionen och alla redigeringar?',
    'Revert to the original image? All edits will be discarded.' => 'Återställa till originalbilden? Alla redigeringar slängs.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Återställa till den här versionen? Alla utkast som skapats efteråt slängs.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'Det gick inte att skapa den konfigurerade redigeringsdrivrutinen. Kontrollera `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} är inte konfigurerad. Lägg till din API-nyckel i `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'Den här sessionen är inte längre aktiv.',
    'Please enter an instruction.' => 'Ange en instruktion.',
    'The edit could not be performed. Please try again.' => 'Redigeringen kunde inte utföras. Försök igen.',
    'The high resolution version could not be generated.' => 'Högupplöst version kunde inte genereras.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Du skickar förfrågningar för snabbt. Vänta en stund och försök igen.',
    'Only image assets can be edited.' => 'Endast bildtillgångar kan redigeras.',
    'Vector images can not be edited.' => 'Vektorbilder kan inte redigeras.',
    'The source image could not be read.' => 'Källbilden kunde inte läsas.',
    'The source image could not be copied into the edit session.' => 'Källbilden kunde inte kopieras till redigeringssessionen.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'Arbetsbilden för den här sessionen finns inte längre. Den kan ha rensats bort, starta en ny session.',
    'The result image could not be read back for storage.' => 'Resultatbilden kunde inte läsas tillbaka för lagring.',
    'The edit was cancelled.' => 'Redigeringen avbröts.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'Det finns inga redigeringar att slutföra i den här sessionen.',
    'There is no result to save in this session.' => 'Det finns inget resultat att spara i den här sessionen.',
    'The result image no longer exists.' => 'Resultatbilden finns inte längre.',
    'The result image could not be prepared for saving.' => 'Resultatbilden kunde inte förberedas för sparande.',
    'The target folder for this session no longer exists.' => 'Målmappen för den här sessionen finns inte längre.',
    'The source asset for this session no longer exists.' => 'Källtillgången för den här sessionen finns inte längre.',
    'The original asset for this session no longer exists.' => 'Originaltillgången för den här sessionen finns inte längre.',
    'The source asset\'s folder could not be resolved.' => 'Källtillgångens mapp kunde inte fastställas.',
    'The original image could not be replaced.' => 'Originalbilden kunde inte ersättas.',
    'The edited image could not be saved as an asset.' => 'Den redigerade bilden kunde inte sparas som en tillgång.',
    'A generated image has no original to replace.' => 'En genererad bild har inget original att ersätta.',
    'The save was cancelled.' => 'Sparandet avbröts.',
];
