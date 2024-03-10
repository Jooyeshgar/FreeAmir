@extends('layouts.app')

@section('content')
<div class="drawer">
  <input id="my-drawer" type="checkbox" class="drawer-toggle">

  <div class="drawer-content">
    <div class="container mx-auto px-4 py-8">
      <h1 class="text-4xl font-bold text-center mb-8">Welcome to Your Accounting Software</h1>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="card bg-base-100 shadow-md">
          <div class="card-body">
            <h5 class="card-title text-xl font-bold">Track Income & Expenses</h5>
            <p>Easily manage your financial inflows and outflows.</p>
            <a href="#" class="btn btn-primary">Learn More</a>
          </div>
        </div>
        <div class="card bg-base-100 shadow-md">
          <div class="card-body">
            <h5 class="card-title text-xl font-bold">Create Invoices</h5>
            <p>Generate professional invoices for your clients.</p>
            <a href="#" class="btn btn-accent">Learn More</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="drawer-side">
    <label for="my-drawer" class="drawer-overlay"></label>
    <ul class="menu p-4 overflow-y-auto bg-base-100">
      <li><a href="#">Dashboard</a></li>
      <li><a href="#">Settings</a></li>
      <li><a href="#">Logout</a></li>
    </ul>
  </div>
</div>
@endsection
