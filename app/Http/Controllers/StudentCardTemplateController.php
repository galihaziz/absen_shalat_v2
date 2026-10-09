<?php

namespace App\Http\Controllers;

use App\Services\StudentCardTemplateStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentCardTemplateController extends Controller
{
    public function __construct(private readonly StudentCardTemplateStorage $templateStorage)
    {
    }

    public function index(): View
    {
        return view('student-card-templates.index', [
            'templateDataUri' => $this->templateStorage->dataUri(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (! $this->templateStorage->store($data['template'])) {
            return back()->withInput()->withErrors(['template' => 'Template kartu gagal disimpan.']);
        }

        return redirect()->route('student-card-templates.index')->with('status', 'Template kartu berhasil disimpan.');
    }

    public function destroy(): RedirectResponse
    {
        $this->templateStorage->delete();

        return redirect()->route('student-card-templates.index')->with('status', 'Template kartu berhasil dihapus.');
    }
}