<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\Prospectus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProspectusController extends Controller
{
    public function edit()
    {
        return view('admin.prospectus.edit', ['current' => Prospectus::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'file' => [Prospectus::exists() ? 'nullable' : 'required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.Prospectus::MAX_KB],
        ], ['file.mimes' => 'The prospectus must be a PDF file.', 'file.mimetypes' => 'The prospectus must be a PDF file.']);

        $current = Prospectus::current() ?? [];
        $disk = Storage::disk('documents');
        if ($file = $request->file('file')) {
            if (! str_starts_with((string) file_get_contents($file->getRealPath(), false, null, 0, 5), '%PDF-')) {
                return back()->withErrors(['file' => 'The prospectus must be a PDF file.'])->withInput();
            }
            $path = $disk->putFileAs('prospectus', $file, 'prospectus-'.now()->format('YmdHis').'-'.Str::random(6).'.pdf');
            if (! empty($current['path'])) {
                $disk->delete($current['path']);
            }
            $current = ['path' => $path, 'name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'uploaded_at' => now()->toIso8601String()];
        }
        Setting::put(Prospectus::KEY, ['title' => $data['title']] + $current, $request->user('admin')->id);
        AuditLog::record('prospectus.update', null, ['title' => $data['title'], 'file' => $current['name'] ?? null]);

        return redirect()->route('admin.prospectus.edit')->with('status', 'Prospectus saved. It is now live on the website.');
    }

    public function destroy(Request $request)
    {
        if ($p = Prospectus::current()) {
            Storage::disk('documents')->delete($p['path']);
        }
        Setting::put(Prospectus::KEY, null, $request->user('admin')->id);
        AuditLog::record('prospectus.delete', null);

        return redirect()->route('admin.prospectus.edit')->with('status', 'Prospectus removed from the website.');
    }
}
