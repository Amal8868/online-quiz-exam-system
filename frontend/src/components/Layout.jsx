import React from 'react';
import { Outlet, Link, useLocation } from 'react-router-dom';
import { HomeIcon, PlusCircleIcon, UserGroupIcon, ChartBarIcon, Cog6ToothIcon } from '@heroicons/react/24/outline';
import ThemeToggle from './ThemeToggle';

const Layout = ({ type = 'default' }) => {
  const location = useLocation();
  
  const isActive = (path) => {
    return location.pathname === path ? 'bg-gray-100 dark:bg-gray-700' : '';
  };

  if (type === 'teacher') {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
        {/* Sidebar */}
        <div className="fixed inset-y-0 left-0 w-64 bg-white dark:bg-gray-800 shadow-lg">
          <div className="flex flex-col h-full">
            {/* Logo */}
            <div className="flex items-center justify-center h-16 px-4 border-b border-gray-200 dark:border-gray-700">
              <h1 className="text-xl font-bold text-primary-600 dark:text-primary-400">QuizMaster</h1>
            </div>
            
            {/* Navigation */}
            <nav className="flex-1 px-2 py-4 space-y-1">
              <Link
                to="/teacher"
                className={`flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-2 ${isActive('/teacher') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'}`}
              >
                <HomeIcon className="w-5 h-5 mr-3" />
                Dashboard
              </Link>
              <Link
                to="/teacher/create-quiz"
                className={`flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-2 ${isActive('/teacher/create-quiz') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'}`}
              >
                <PlusCircleIcon className="w-5 h-5 mr-3" />
                Create Quiz
              </Link>
              <Link
                to="#"
                className="flex items-center px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg mx-2 hover:bg-gray-100 dark:hover:bg-gray-700"
              >
                <UserGroupIcon className="w-5 h-5 mr-3" />
                My Students
              </Link>
              <Link
                to="#"
                className="flex items-center px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg mx-2 hover:bg-gray-100 dark:hover:bg-gray-700"
              >
                <ChartBarIcon className="w-5 h-5 mr-3" />
                Results
              </Link>
            </nav>
            
            {/* Theme Toggle and Settings */}
            <div className="p-4 border-t border-gray-200 dark:border-gray-700">
              <div className="flex items-center justify-between">
                <ThemeToggle />
                <button className="p-2 text-gray-500 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                  <Cog6ToothIcon className="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
        
        {/* Main Content */}
        <div className="pl-64">
          <main className="p-6">
            <Outlet />
          </main>
        </div>
      </div>
    );
  }
  
  // Default layout for public pages
  return (
    <div className="min-h-screen bg-white dark:bg-gray-900 transition-colors duration-200">
      <header className="bg-white dark:bg-gray-800 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <Link to="/" className="text-xl font-bold text-primary-600 dark:text-primary-400">
                QuizMaster
              </Link>
            </div>
            <div className="flex items-center space-x-4">
              <ThemeToggle />
              <Link 
                to="/teacher/login" 
                className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400"
              >
                Teacher Login
              </Link>
              <Link 
                to="/join" 
                className="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
              >
                Join as Student
              </Link>
            </div>
          </div>
        </div>
      </header>
      
      <main>
        <Outlet />
      </main>
      
      <footer className="bg-white dark:bg-gray-800 mt-12 border-t border-gray-200 dark:border-gray-700">
        <div className="max-w-7xl mx-auto py-6 px-4 overflow-hidden sm:px-6 lg:px-8">
          <p className="text-center text-sm text-gray-500 dark:text-gray-400">
            &copy; {new Date().getFullYear()} QuizMaster. All rights reserved.
          </p>
        </div>
      </footer>
    </div>
  );
};

export default Layout;
