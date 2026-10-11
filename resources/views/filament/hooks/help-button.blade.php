{{-- Opens the user guide on the topic for this screen (HelpController::forScreen). --}}
<x-filament::icon-button
    tag="a"
    :href="'/help/for?path=' . urlencode(request()->path())"
    target="_blank"
    icon="heroicon-o-question-mark-circle"
    color="gray"
    size="lg"
    :label="__('Help for this page')"
    :tooltip="__('Help for this page')"
/>
