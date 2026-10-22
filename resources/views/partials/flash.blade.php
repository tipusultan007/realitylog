@if (session('status') || $errors->any())
    <div class="flash-stack" aria-live="polite">
        @if (session('status'))
            <div class="alert alert-success flash" data-flash>
                <span><i class="bi bi-check2-circle me-1"></i> {{ session('status') }}</span>
                <button type="button" onclick="this.closest('.flash').remove()" aria-label="{{ __('ui.common.cancel') }}">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger flash" data-flash>
                <span>
                    <i class="bi bi-exclamation-circle me-1"></i>
                    {{ $errors->first() }}
                </span>
                <button type="button" onclick="this.closest('.flash').remove()" aria-label="{{ __('ui.common.cancel') }}">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        @endif
    </div>
@endif
