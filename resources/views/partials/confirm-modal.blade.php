<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="confirmModalLabel" data-confirm-title>{{ __('ui.common.are_you_sure') }}</h2>
                <button type="button" class="icon-btn-plain" data-bs-dismiss="modal" aria-label="{{ __('ui.common.cancel') }}">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <p class="mb-0 text-muted-soft" data-confirm-message></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">{{ __('ui.common.cancel') }}</button>
                <button type="button" class="btn btn-danger btn-sm" data-confirm-action>{{ __('ui.common.confirm') }}</button>
            </div>
        </div>
    </div>
</div>
