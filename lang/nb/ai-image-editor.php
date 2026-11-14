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
 * Norwegian Bokmål (nb) translations for the `ai-image-editor` category.
 * Machine-generated baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Rediger bilder med AI',
    'Generate images with AI' => 'Generer bilder med AI',
    'Edit with AI' => 'Rediger med AI',
    'Purging stale AI image edit sessions' => 'Rydder opp i gamle AI-bilderedigeringsøkter',

    // Editor: buttons and actions
    'Save' => 'Lagre',
    'Save as a new asset' => 'Lagre som ny fil',
    'Accept & Save' => 'Godta og lagre',
    'Discard' => 'Forkast',
    'Apply' => 'Bruk',
    'Generate' => 'Generer',
    'Retry' => 'Prøv igjen',
    'Back to editing' => 'Tilbake til redigering',
    'Revert to original' => 'Tilbakestill til original',
    'Revert to this version' => 'Tilbakestill til denne versjonen',
    'Quick actions' => 'Hurtighandlinger',

    // Editor: composer labels and controls
    'Precise edits' => 'Presise redigeringer',
    'Model' => 'Modell',
    'Aspect ratio' => 'Sideforhold',
    'Output format' => 'Utdataformat',
    'Final resolution' => 'Endelig oppløsning',
    'Match original' => 'Som original',
    'Auto' => 'Automatisk',
    'Draft' => 'Utkast',
    'Final' => 'Endelig',
    'Original' => 'Original',
    'Turn {number}' => 'Steg {number}',
    'and' => 'og',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Beskriv endringen du vil gjøre…',
    'Describe the image you want to create…' => 'Beskriv bildet du vil lage…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Beskriv bildet du vil lage, og bruk for å generere det første utkastet.',
    'Compare the draft and final versions, then choose which one to save.' => 'Sammenlign utkastet og den endelige versjonen, og velg hvilken du vil lagre.',
    'Images and prompts are sent to {provider} for processing.' => 'Bilder og ledetekster sendes til {provider} for behandling.',
    'Working…' => 'Arbeider…',
    'Generating the final version…' => 'Genererer den endelige versjonen…',
    'Edited image saved as {filename}.' => 'Redigert bilde lagret som {filename}.',
    'The image could not be loaded.' => 'Bildet kunne ikke lastes.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Forkaste denne økten og alle redigeringer?',
    'Revert to the original image? All edits will be discarded.' => 'Tilbakestille til originalbildet? Alle redigeringer forkastes.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Tilbakestille til denne versjonen? Alle utkast laget etter den forkastes.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'Kunne ikke opprette den konfigurerte redigeringsdriveren. Sjekk `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} er ikke konfigurert. Legg til API-nøkkelen din i `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'Denne økten er ikke lenger aktiv.',
    'Please enter an instruction.' => 'Skriv inn en instruksjon.',
    'The edit could not be performed. Please try again.' => 'Redigeringen kunne ikke utføres. Prøv igjen.',
    'The high resolution version could not be generated.' => 'Høyoppløselig versjon kunne ikke genereres.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Du sender forespørsler for raskt. Vent et øyeblikk og prøv igjen.',
    'Only image assets can be edited.' => 'Bare bildefiler kan redigeres.',
    'Vector images can not be edited.' => 'Vektorbilder kan ikke redigeres.',
    'The source image could not be read.' => 'Kildebildet kunne ikke leses.',
    'The source image could not be copied into the edit session.' => 'Kildebildet kunne ikke kopieres inn i redigeringsøkten.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'Arbeidsbildet for denne økten finnes ikke lenger. Det kan ha blitt ryddet bort, start en ny økt.',
    'The result image could not be read back for storage.' => 'Resultatbildet kunne ikke leses tilbake for lagring.',
    'The edit was cancelled.' => 'Redigeringen ble avbrutt.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'Det er ingen redigeringer å fullføre i denne økten.',
    'There is no result to save in this session.' => 'Det er ingen resultat å lagre i denne økten.',
    'The result image no longer exists.' => 'Resultatbildet finnes ikke lenger.',
    'The result image could not be prepared for saving.' => 'Resultatbildet kunne ikke klargjøres for lagring.',
    'The target folder for this session no longer exists.' => 'Målmappen for denne økten finnes ikke lenger.',
    'The source asset for this session no longer exists.' => 'Kildefilen for denne økten finnes ikke lenger.',
    'The original asset for this session no longer exists.' => 'Originalfilen for denne økten finnes ikke lenger.',
    'The source asset\'s folder could not be resolved.' => 'Kildefilens mappe kunne ikke bestemmes.',
    'The original image could not be replaced.' => 'Originalbildet kunne ikke erstattes.',
    'The edited image could not be saved as an asset.' => 'Det redigerte bildet kunne ikke lagres som fil.',
    'A generated image has no original to replace.' => 'Et generert bilde har ingen original å erstatte.',
    'The save was cancelled.' => 'Lagringen ble avbrutt.',
];
