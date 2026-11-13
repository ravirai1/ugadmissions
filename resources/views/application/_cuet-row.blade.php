<tr data-row>
    <td>
        <select class="form-select form-select-sm" name="scores[{{ $i }}][subject]" data-cuet-subject>
            <option value="">Select paper</option>
            @foreach ($subjects as $type => $list)
                <optgroup label="{{ $typeLabels[$type] ?? $type }}">
                    @foreach ($list as $s)
                        <option value="{{ $s->id }}" data-unmatched="{{ $s->type === 'domain' && ! in_array($s->id, $covered, true) ? 1 : 0 }}" @selected((string) ($r['subject'] ?? '') === (string) $s->id)>{{ $s->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </td>
    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="scores[{{ $i }}][score]" value="{{ $r['score'] ?? '' }}"></td>
    <td class="small" data-match></td>
    <td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-row aria-label="Remove"><i class="fa fa-times"></i></button></td>
</tr>
