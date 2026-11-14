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
 * Polish (pl) translations for the `ai-image-editor` category. Machine-generated
 * baseline; a native review pass is recommended.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Edytuj obrazy za pomocą AI',
    'Generate images with AI' => 'Generuj obrazy za pomocą AI',
    'Edit with AI' => 'Edytuj za pomocą AI',
    'Purging stale AI image edit sessions' => 'Czyszczenie nieaktualnych sesji edycji obrazów AI',

    // Editor: buttons and actions
    'Save' => 'Zapisz',
    'Save as a new asset' => 'Zapisz jako nowy zasób',
    'Accept & Save' => 'Zaakceptuj i zapisz',
    'Discard' => 'Odrzuć',
    'Apply' => 'Zastosuj',
    'Generate' => 'Generuj',
    'Retry' => 'Spróbuj ponownie',
    'Back to editing' => 'Powrót do edycji',
    'Revert to original' => 'Przywróć oryginał',
    'Revert to this version' => 'Przywróć tę wersję',
    'Quick actions' => 'Szybkie akcje',

    // Editor: composer labels and controls
    'Precise edits' => 'Precyzyjne edycje',
    'Model' => 'Model',
    'Aspect ratio' => 'Proporcje obrazu',
    'Output format' => 'Format wyjściowy',
    'Final resolution' => 'Rozdzielczość końcowa',
    'Match original' => 'Jak oryginał',
    'Auto' => 'Automatycznie',
    'Draft' => 'Szkic',
    'Final' => 'Końcowa',
    'Original' => 'Oryginał',
    'Turn {number}' => 'Krok {number}',
    'and' => 'i',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Opisz zmianę, którą chcesz wprowadzić…',
    'Describe the image you want to create…' => 'Opisz obraz, który chcesz utworzyć…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Opisz obraz, który chcesz utworzyć, a następnie zastosuj, aby wygenerować pierwszy szkic.',
    'Compare the draft and final versions, then choose which one to save.' => 'Porównaj szkic i wersję końcową, a następnie wybierz, którą zapisać.',
    'Images and prompts are sent to {provider} for processing.' => 'Obrazy i prompty są wysyłane do {provider} w celu przetworzenia.',
    'Working…' => 'Przetwarzanie…',
    'Generating the final version…' => 'Generowanie wersji końcowej…',
    'Edited image saved as {filename}.' => 'Edytowany obraz zapisany jako {filename}.',
    'The image could not be loaded.' => 'Nie można załadować obrazu.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Odrzucić tę sesję i wszystkie edycje?',
    'Revert to the original image? All edits will be discarded.' => 'Przywrócić oryginalny obraz? Wszystkie edycje zostaną odrzucone.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Przywrócić tę wersję? Wszystkie szkice utworzone później zostaną odrzucone.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'Nie można utworzyć skonfigurowanego sterownika edycji. Sprawdź `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} nie jest skonfigurowany. Dodaj swój klucz API w `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'Ta sesja nie jest już aktywna.',
    'Please enter an instruction.' => 'Wprowadź instrukcję.',
    'The edit could not be performed. Please try again.' => 'Nie można wykonać edycji. Spróbuj ponownie.',
    'The high resolution version could not be generated.' => 'Nie można wygenerować wersji w wysokiej rozdzielczości.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'Wysyłasz żądania zbyt szybko. Poczekaj chwilę i spróbuj ponownie.',
    'Only image assets can be edited.' => 'Można edytować tylko zasoby obrazów.',
    'Vector images can not be edited.' => 'Obrazów wektorowych nie można edytować.',
    'The source image could not be read.' => 'Nie można odczytać obrazu źródłowego.',
    'The source image could not be copied into the edit session.' => 'Nie można skopiować obrazu źródłowego do sesji edycji.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'Obraz roboczy dla tej sesji już nie istnieje. Mógł zostać usunięty, rozpocznij nową sesję.',
    'The result image could not be read back for storage.' => 'Nie można odczytać obrazu wynikowego do zapisu.',
    'The edit was cancelled.' => 'Edycja została anulowana.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'W tej sesji nie ma edycji do sfinalizowania.',
    'There is no result to save in this session.' => 'W tej sesji nie ma wyniku do zapisania.',
    'The result image no longer exists.' => 'Obraz wynikowy już nie istnieje.',
    'The result image could not be prepared for saving.' => 'Nie można przygotować obrazu wynikowego do zapisu.',
    'The target folder for this session no longer exists.' => 'Folder docelowy dla tej sesji już nie istnieje.',
    'The source asset for this session no longer exists.' => 'Zasób źródłowy dla tej sesji już nie istnieje.',
    'The original asset for this session no longer exists.' => 'Oryginalny zasób dla tej sesji już nie istnieje.',
    'The source asset\'s folder could not be resolved.' => 'Nie można ustalić folderu zasobu źródłowego.',
    'The original image could not be replaced.' => 'Nie można zastąpić oryginalnego obrazu.',
    'The edited image could not be saved as an asset.' => 'Nie można zapisać edytowanego obrazu jako zasobu.',
    'A generated image has no original to replace.' => 'Wygenerowany obraz nie ma oryginału do zastąpienia.',
    'The save was cancelled.' => 'Zapisywanie zostało anulowane.',
];
