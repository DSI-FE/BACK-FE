<?php

namespace App\Http\Controllers\API\Administration;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmployeeBirthdayGreetingsJob;
use App\Models\Administration\EmployeeBirthdayGreetingSent;
use Illuminate\Http\Request;

class EmployeeBirthdayGreetingSentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        SendEmployeeBirthdayGreetingsJob::dispatch();
        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeBirthdayGreetingSent $employeeGreetingSent)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmployeeBirthdayGreetingSent $employeeGreetingSent)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmployeeBirthdayGreetingSent $employeeGreetingSent)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeBirthdayGreetingSent $employeeGreetingSent)
    {
        //
    }
}
