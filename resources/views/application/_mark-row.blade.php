<tr data-row>
    <td>
        <select class="form-select form-select-sm" name="marks[{{ $i }}][subject]">
            <option value="">Select subject</option>
            @foreach ($class12Subjects as $s)<option value="{{ $s->id }}" @selected((string) ($r['subject'] ?? '') === (string) $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </td>
    <td><input type="number" class="form-control form-control-sm" name="marks[{{ $i }}][max]" value="{{ $r['max'] ?? 100 }}" data-max></td>
    <td><input type="number" step="0.01" class="form-control form-control-sm" name="marks[{{ $i }}][obtained]" value="{{ $r['obtained'] ?? '' }}" data-obtained></td>
    <td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-row aria-label="Remove"><i class="fa fa-times"></i></button></td>
</tr>
