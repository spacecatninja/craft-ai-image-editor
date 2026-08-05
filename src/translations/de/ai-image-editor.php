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
 * German (de) translations for the `ai-image-editor` category. Machine-generated
 * baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Bilder mit AI bearbeiten',
    'Generate images with AI' => 'Bilder mit AI generieren',
    'Edit with AI' => 'Mit AI bearbeiten',
    'Purging stale AI image edit sessions' => 'Veraltete AI-Bildbearbeitungssitzungen werden bereinigt',

    // Editor: buttons and actions
    'Save' => 'Speichern',
    'Save as a new asset' => 'Als neue Datei speichern',
    'Accept & Save' => 'Übernehmen & speichern',
    'Discard' => 'Verwerfen',
    'Apply' => 'Anwenden',
    'Generate' => 'Generieren',
    'Retry' => 'Erneut versuchen',
    'Back to editing' => 'Zurück zur Bearbeitung',
    'Revert to original' => 'Auf Original zurücksetzen',
    'Revert to this version' => 'Auf diese Version zurücksetzen',
    'Quick actions' => 'Schnellaktionen',

    // Editor: composer labels and controls
    'Precise edits' => 'Präzise Bearbeitung',
    'Model' => 'Modell',
    'Aspect ratio' => 'Seitenverhältnis',
    'Output format' => 'Ausgabeformat',
    'Final resolution' => 'Finale Auflösung',
    'Match original' => 'Wie Original',
    'Auto' => 'Automatisch',
    'Draft' => 'Entwurf',
    'Final' => 'Final',
    'Original' => 'Original',
    'Turn {number}' => 'Schritt {number}',
    'and' => 'und',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Gewünschte Änderung beschreiben…',
    'Describe the image you want to create…' => 'Gewünschtes Bild beschreiben…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Beschreibe das gewünschte Bild und wende es an, um den ersten Entwurf zu generieren.',
    'Compare the draft and final versions, then choose which one to save.' => 'Vergleiche Entwurf und finale Version und wähle, welche gespeichert werden soll.',
    'Images and prompts are sent to {provider} for processing.' => 'Bilder und Prompts werden zur Verarbeitung an {provider} gesendet.',
    'Working…' => 'Wird bearbeitet…',
    'Generating the final version…' => 'Finale Version wird generiert…',
    'Edited image saved as {filename}.' => 'Bearbeitetes Bild als {filename} gespeichert.',
    'The image could not be loaded.' => 'Das Bild konnte nicht geladen werden.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Diese Sitzung und alle Bearbeitungen verwerfen?',
    'Revert to the original image? All edits will be discarded.' => 'Auf das Originalbild zurücksetzen? Alle Bearbeitungen werden verworfen.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Auf diese Version zurücksetzen? Alle danach erstellten Entwürfe werden verworfen.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'Der konfigurierte Bearbeitungstreiber konnte nicht erstellt werden. Prüfe `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} ist nicht konfiguriert. Füge deinen API-Schlüssel in `config/ai-image-editor.php` hinzu.',

    // Session / turn flow
    'This session is no longer active.' => 'Diese Sitzung ist nicht mehr aktiv.',
    'Please enter an instruction.' => 'Bitte gib eine Anweisung ein.',
    'The edit could not be performed. Please try again.' => 'Die Bearbeitung konnte nicht durchgeführt werden. Bitte versuche es erneut.',
    'The high resolution version could not be generated.' => 'Die hochauflösende Version konnte nicht generiert werden.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Du sendest zu schnell Anfragen. Bitte warte einen Moment und versuche es erneut.',
    'Only image assets can be edited.' => 'Nur Bilddateien können bearbeitet werden.',
    'Vector images can not be edited.' => 'Vektorbilder können nicht bearbeitet werden.',
    'The source image could not be read.' => 'Das Quellbild konnte nicht gelesen werden.',
    'The source image could not be copied into the edit session.' => 'Das Quellbild konnte nicht in die Bearbeitungssitzung kopiert werden.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'Das Arbeitsbild für diese Sitzung existiert nicht mehr. Es wurde möglicherweise bereinigt, bitte starte eine neue Sitzung.',
    'The result image could not be read back for storage.' => 'Das Ergebnisbild konnte nicht zum Speichern zurückgelesen werden.',
    'The edit was cancelled.' => 'Die Bearbeitung wurde abgebrochen.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'In dieser Sitzung gibt es keine Bearbeitungen zum Fertigstellen.',
    'There is no result to save in this session.' => 'In dieser Sitzung gibt es kein Ergebnis zum Speichern.',
    'The result image no longer exists.' => 'Das Ergebnisbild existiert nicht mehr.',
    'The result image could not be prepared for saving.' => 'Das Ergebnisbild konnte nicht zum Speichern vorbereitet werden.',
    'The target folder for this session no longer exists.' => 'Der Zielordner für diese Sitzung existiert nicht mehr.',
    'The source asset for this session no longer exists.' => 'Die Quelldatei für diese Sitzung existiert nicht mehr.',
    'The original asset for this session no longer exists.' => 'Die Originaldatei für diese Sitzung existiert nicht mehr.',
    'The source asset\'s folder could not be resolved.' => 'Der Ordner der Quelldatei konnte nicht ermittelt werden.',
    'The original image could not be replaced.' => 'Das Originalbild konnte nicht ersetzt werden.',
    'The edited image could not be saved as an asset.' => 'Das bearbeitete Bild konnte nicht als Datei gespeichert werden.',
    'A generated image has no original to replace.' => 'Ein generiertes Bild hat kein Original, das ersetzt werden könnte.',
    'The save was cancelled.' => 'Das Speichern wurde abgebrochen.',
];
