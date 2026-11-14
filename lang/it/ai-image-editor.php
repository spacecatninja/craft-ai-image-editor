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
 * Italian (it) translations for the `ai-image-editor` category. Machine-generated
 * baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Modifica immagini con l\'AI',
    'Generate images with AI' => 'Genera immagini con l\'AI',
    'Edit with AI' => 'Modifica con l\'AI',
    'Purging stale AI image edit sessions' => 'Pulizia delle sessioni di modifica immagini AI obsolete',

    // Editor: buttons and actions
    'Save' => 'Salva',
    'Save as a new asset' => 'Salva come nuova risorsa',
    'Accept & Save' => 'Accetta e salva',
    'Discard' => 'Scarta',
    'Apply' => 'Applica',
    'Generate' => 'Genera',
    'Retry' => 'Riprova',
    'Back to editing' => 'Torna alla modifica',
    'Revert to original' => 'Ripristina l\'originale',
    'Revert to this version' => 'Ripristina questa versione',
    'Quick actions' => 'Azioni rapide',

    // Editor: composer labels and controls
    'Precise edits' => 'Modifiche precise',
    'Model' => 'Modello',
    'Aspect ratio' => 'Proporzioni',
    'Output format' => 'Formato di output',
    'Final resolution' => 'Risoluzione finale',
    'Match original' => 'Come l\'originale',
    'Auto' => 'Automatico',
    'Draft' => 'Bozza',
    'Final' => 'Finale',
    'Original' => 'Originale',
    'Turn {number}' => 'Passaggio {number}',
    'and' => 'e',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Descrivi la modifica che vuoi apportare…',
    'Describe the image you want to create…' => 'Descrivi l\'immagine che vuoi creare…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Descrivi l\'immagine che vuoi creare, poi applica per generare la prima bozza.',
    'Compare the draft and final versions, then choose which one to save.' => 'Confronta la bozza e la versione finale, poi scegli quale salvare.',
    'Images and prompts are sent to {provider} for processing.' => 'Le immagini e i prompt vengono inviati a {provider} per l\'elaborazione.',
    'Working…' => 'Elaborazione…',
    'Generating the final version…' => 'Generazione della versione finale…',
    'Edited image saved as {filename}.' => 'Immagine modificata salvata come {filename}.',
    'The image could not be loaded.' => 'Impossibile caricare l\'immagine.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Scartare questa sessione e tutte le modifiche?',
    'Revert to the original image? All edits will be discarded.' => 'Ripristinare l\'immagine originale? Tutte le modifiche verranno scartate.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Ripristinare questa versione? Tutte le bozze create successivamente verranno scartate.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'Impossibile creare il driver di modifica configurato. Controlla `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} non è configurato. Aggiungi la tua chiave API in `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'Questa sessione non è più attiva.',
    'Please enter an instruction.' => 'Inserisci un\'istruzione.',
    'The edit could not be performed. Please try again.' => 'Impossibile eseguire la modifica. Riprova.',
    'The high resolution version could not be generated.' => 'Impossibile generare la versione ad alta risoluzione.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Stai inviando richieste troppo velocemente. Attendi un momento e riprova.',
    'Only image assets can be edited.' => 'È possibile modificare solo risorse immagine.',
    'Vector images can not be edited.' => 'Le immagini vettoriali non possono essere modificate.',
    'The source image could not be read.' => 'Impossibile leggere l\'immagine di origine.',
    'The source image could not be copied into the edit session.' => 'Impossibile copiare l\'immagine di origine nella sessione di modifica.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'L\'immagine di lavoro di questa sessione non esiste più. Potrebbe essere stata eliminata, avvia una nuova sessione.',
    'The result image could not be read back for storage.' => 'Impossibile rileggere l\'immagine risultante per l\'archiviazione.',
    'The edit was cancelled.' => 'La modifica è stata annullata.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'Non ci sono modifiche da finalizzare in questa sessione.',
    'There is no result to save in this session.' => 'Non c\'è alcun risultato da salvare in questa sessione.',
    'The result image no longer exists.' => 'L\'immagine risultante non esiste più.',
    'The result image could not be prepared for saving.' => 'Impossibile preparare l\'immagine risultante per il salvataggio.',
    'The target folder for this session no longer exists.' => 'La cartella di destinazione di questa sessione non esiste più.',
    'The source asset for this session no longer exists.' => 'La risorsa di origine di questa sessione non esiste più.',
    'The original asset for this session no longer exists.' => 'La risorsa originale di questa sessione non esiste più.',
    'The source asset\'s folder could not be resolved.' => 'Impossibile determinare la cartella della risorsa di origine.',
    'The original image could not be replaced.' => 'Impossibile sostituire l\'immagine originale.',
    'The edited image could not be saved as an asset.' => 'Impossibile salvare l\'immagine modificata come risorsa.',
    'A generated image has no original to replace.' => 'Un\'immagine generata non ha un originale da sostituire.',
    'The save was cancelled.' => 'Il salvataggio è stato annullato.',
];
