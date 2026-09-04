<?php

namespace App\Http\Controllers;

use App\Models\SolutionsList;
use Illuminate\Contracts\View\View;

class SolutionsListPrintController extends Controller
{
    public function __invoke(SolutionsList $solutionsList): View
    {
        $solutionsList->load(['customer', 'items', 'createdBy']);

        return view('solutions-lists.print', ['list' => $solutionsList]);
    }
}
