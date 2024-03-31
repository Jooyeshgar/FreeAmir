<header class="bg-base-100 shadow">
      <div class="container mx-auto px-4 flex justify-between items-center h-16">
        <label for="my-drawer" class="btn btn-sm drawer-button">
          <svg class="w-6 h-6" fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </label>
        <a href="#" class="text-xl font-bold">FreeAmir</a>
        <ul class="flex space-x-4">
          <li><a href="/" class="hover:text-gray-500">Home</a></li>
          <li><a href="{{ route('subjects.index') }}" class="hover:text-gray-500">Subjects</a></li>
          <li><a href="{{ route('transactions.index') }}" class="hover:text-gray-500">Transactions</a></li>
          <li><a href="{{ route('products.index') }}" class="hover:text-gray-500">Products</a></li>
          <li><a href="{{ route('customers.index') }}" class="hover:text-gray-500">Customers</a></li>
          <li><a href="{{ route('customer-groups.index') }}" class="hover:text-gray-500">Customer Groups</a></li>
          <li><a href="{{ route('bank-accounts.index') }}" class="hover:text-gray-500">Bank Accounts</a></li>
          <!-- <li><a href="#" class="hover:text-gray-500">Contact</a></li> -->
        </ul>
      </div>
    </header>
