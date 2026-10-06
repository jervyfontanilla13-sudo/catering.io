<?php

namespace App\Http\Controllers;

use App\Support\HelpCenterContent;

class AdminHelpController extends Controller
{
    public function support()
    {
        $guestCategories = HelpCenterContent::guestHelpCategories();
        $adminCategories = HelpCenterContent::adminHelpCategories();
        $chapters = HelpCenterContent::adminManualChapters();

        return view('admin.help', compact('guestCategories', 'adminCategories', 'chapters'));
    }

    public function help()
    {
        return redirect()->route('admin.support');
    }

    public function manual()
    {
        return redirect()->route('admin.support');
    }
}
