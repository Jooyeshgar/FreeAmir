@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
  <h1 class="text-4xl font-bold text-center mb-8">Welcome to Your Accounting Software</h1>
  <div class="flex flex-col md:flex-row md:space-x-8">
    <div class="w-full md:w-1/2">
      <img src="images/accounting.svg" alt="Accounting illustration" class="mx-auto mb-4 rounded-lg shadow-md">
      <p class="text-lg leading-loose text-gray-700">
        This is your one-stop shop for managing your finances. Easily track your income and expenses, create invoices, and generate reports. Get organized and take control of your financial health today!
      </p>
    </div>
    <div class="w-full md:w-1/2 flex flex-col space-y-4">
      <a href="#" class="px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white font-bold rounded-md">Get Started</a>
      <a href="#" class="px-4 py-2 border border-gray-300 hover:border-gray-500 text-gray-700 font-bold rounded-md">Learn More</a>
    </div>
  </div>
</div>
@endsection