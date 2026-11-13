<div class="d-flex justify-content-between mt-3">
    @if ($prev = \App\Support\ApplicationSteps::previous($step))
        <a href="{{ route('application.step', $prev) }}" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Back</a>
    @else <span></span> @endif
    <button type="submit" class="btn btn-primary px-4">Save &amp; Continue <i class="fa fa-arrow-right ms-1"></i></button>
</div>
