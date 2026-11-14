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
 * Dutch (nl) translations for the `ai-image-editor` category. Machine-generated
 * baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Afbeeldingen bewerken met AI',
    'Generate images with AI' => 'Afbeeldingen genereren met AI',
    'Edit with AI' => 'Bewerken met AI',
    'Purging stale AI image edit sessions' => 'Verouderde AI-beeldbewerkingssessies opschonen',

    // Editor: buttons and actions
    'Save' => 'Opslaan',
    'Save as a new asset' => 'Opslaan als nieuw bestand',
    'Accept & Save' => 'Accepteren & opslaan',
    'Discard' => 'Verwerpen',
    'Apply' => 'Toepassen',
    'Generate' => 'Genereren',
    'Retry' => 'Opnieuw proberen',
    'Back to editing' => 'Terug naar bewerken',
    'Revert to original' => 'Terugzetten naar origineel',
    'Revert to this version' => 'Terugzetten naar deze versie',
    'Quick actions' => 'Snelle acties',

    // Editor: composer labels and controls
    'Precise edits' => 'Precieze bewerkingen',
    'Model' => 'Model',
    'Aspect ratio' => 'Beeldverhouding',
    'Output format' => 'Uitvoerformaat',
    'Final resolution' => 'Definitieve resolutie',
    'Match original' => 'Zoals origineel',
    'Auto' => 'Automatisch',
    'Draft' => 'Concept',
    'Final' => 'Definitief',
    'Original' => 'Origineel',
    'Turn {number}' => 'Stap {number}',
    'and' => 'en',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Beschrijf de gewenste wijziging…',
    'Describe the image you want to create…' => 'Beschrijf de afbeelding die je wilt maken…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Beschrijf de afbeelding die je wilt maken en pas toe om het eerste concept te genereren.',
    'Compare the draft and final versions, then choose which one to save.' => 'Vergelijk het concept en de definitieve versie en kies welke je wilt opslaan.',
    'Images and prompts are sent to {provider} for processing.' => 'Afbeeldingen en prompts worden ter verwerking naar {provider} verzonden.',
    'Working…' => 'Bezig…',
    'Generating the final version…' => 'Definitieve versie genereren…',
    'Edited image saved as {filename}.' => 'Bewerkte afbeelding opgeslagen als {filename}.',
    'The image could not be loaded.' => 'De afbeelding kon niet worden geladen.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Deze sessie en alle bewerkingen verwerpen?',
    'Revert to the original image? All edits will be discarded.' => 'Terugzetten naar de originele afbeelding? Alle bewerkingen worden verworpen.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Terugzetten naar deze versie? Alle concepten die erna zijn gemaakt worden verworpen.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'De geconfigureerde bewerkingsdriver kon niet worden aangemaakt. Controleer `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} is niet geconfigureerd. Voeg je API-sleutel toe in `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'Deze sessie is niet langer actief.',
    'Please enter an instruction.' => 'Voer een instructie in.',
    'The edit could not be performed. Please try again.' => 'De bewerking kon niet worden uitgevoerd. Probeer het opnieuw.',
    'The high resolution version could not be generated.' => 'De hogeresolutieversie kon niet worden gegenereerd.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Je verstuurt te snel verzoeken. Wacht even en probeer het opnieuw.',
    'Only image assets can be edited.' => 'Alleen afbeeldingsbestanden kunnen worden bewerkt.',
    'Vector images can not be edited.' => 'Vectorafbeeldingen kunnen niet worden bewerkt.',
    'The source image could not be read.' => 'De bronafbeelding kon niet worden gelezen.',
    'The source image could not be copied into the edit session.' => 'De bronafbeelding kon niet naar de bewerkingssessie worden gekopieerd.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'De werkafbeelding voor deze sessie bestaat niet meer. Mogelijk is deze opgeschoond, start een nieuwe sessie.',
    'The result image could not be read back for storage.' => 'De resultaatafbeelding kon niet worden teruggelezen om op te slaan.',
    'The edit was cancelled.' => 'De bewerking is geannuleerd.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'Er zijn geen bewerkingen om af te ronden in deze sessie.',
    'There is no result to save in this session.' => 'Er is geen resultaat om op te slaan in deze sessie.',
    'The result image no longer exists.' => 'De resultaatafbeelding bestaat niet meer.',
    'The result image could not be prepared for saving.' => 'De resultaatafbeelding kon niet worden voorbereid om op te slaan.',
    'The target folder for this session no longer exists.' => 'De doelmap voor deze sessie bestaat niet meer.',
    'The source asset for this session no longer exists.' => 'Het bronbestand voor deze sessie bestaat niet meer.',
    'The original asset for this session no longer exists.' => 'Het originele bestand voor deze sessie bestaat niet meer.',
    'The source asset\'s folder could not be resolved.' => 'De map van het bronbestand kon niet worden bepaald.',
    'The original image could not be replaced.' => 'De originele afbeelding kon niet worden vervangen.',
    'The edited image could not be saved as an asset.' => 'De bewerkte afbeelding kon niet als bestand worden opgeslagen.',
    'A generated image has no original to replace.' => 'Een gegenereerde afbeelding heeft geen origineel om te vervangen.',
    'The save was cancelled.' => 'Het opslaan is geannuleerd.',
];
