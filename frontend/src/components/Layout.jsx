import React, { useState, useEffect } from 'react';
import { Outlet, Link, useLocation, useNavigate } from 'react-router-dom';
import { HomeIcon, PlusCircleIcon, ChartBarIcon, AcademicCapIcon, PencilSquareIcon, ArrowLeftOnRectangleIcon } from '@heroicons/react/24/outline';
import ThemeToggle from './ThemeToggle';
import { teacherAPI } from '../services/api';

const Layout = ({ type = 'default' }) => {
  const location = useLocation();
  const navigate = useNavigate();
  const [persistedQuizId, setPersistedQuizId] = useState(localStorage.getItem('lastQuizId'));

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('lastQuizId');
    navigate('/teacher/login');
  };

  const isActive = (path) => {
    return location.pathname === path ? 'bg-gray-100 dark:bg-gray-700' : '';
  };

  // Extract quizId ONLY if we're in the 'Manage Quiz' section
  // This prevents the sidebar from switching context when viewing results in the Results section
  const isManageSection = location.pathname.startsWith('/teacher/quiz/');
  const quizIdMatch = isManageSection ? location.pathname.match(/\/quiz\/([^/?#]+)/) : null;
  const currentQuizId = quizIdMatch ? quizIdMatch[1] : null;

  useEffect(() => {
    if (currentQuizId) {
      localStorage.setItem('lastQuizId', currentQuizId);
      setPersistedQuizId(currentQuizId);
    } else if (!persistedQuizId && type === 'teacher') {
      // Try to fetch the latest quiz as a default
      teacherAPI.getQuizzes().then(res => {
        if (res.data.data && res.data.data.length > 0) {
          const firstQuizId = res.data.data[0].id;
          localStorage.setItem('lastQuizId', firstQuizId);
          setPersistedQuizId(firstQuizId);
        }
      }).catch(() => { });
    }
  }, [currentQuizId, type, persistedQuizId]);

  const quizId = currentQuizId || persistedQuizId;

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
                Create quiz
              </Link>
              <Link
                to="/teacher/classes"
                className={`flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-2 ${isActive('/teacher/classes') || location.pathname.includes('/teacher/classes') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'}`}
              >
                <AcademicCapIcon className="w-5 h-5 mr-3" />
                Classes
              </Link>
              <Link
                to={quizId ? `/teacher/quiz/${quizId}` : "/teacher"}
                className={`flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-2 ${location.pathname.startsWith('/teacher/quiz/') ? 'text-primary-600 dark:text-primary-400 bg-gray-100 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'}`}
              >
                <PencilSquareIcon className="w-5 h-5 mr-3" />
                {quizId ? 'Manage Quiz' : 'Manage'}
              </Link>
              <Link
                to="/teacher/results"
                className={`flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-2 ${isActive('/teacher/results') || location.pathname.includes('/teacher/results') ? 'text-primary-600 dark:text-primary-400 bg-gray-100 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'}`}
              >
                <ChartBarIcon className="w-5 h-5 mr-3" />
                Results
              </Link>
            </nav>

            <div className="p-4 border-t border-gray-200 dark:border-gray-700">
              <div className="flex items-center justify-between">
                <ThemeToggle />
                <div className="flex items-center space-x-1">
                  <button
                    onClick={handleLogout}
                    className="p-2 text-red-500 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20"
                    title="Logout"
                  >
                    <ArrowLeftOnRectangleIcon className="w-5 h-5" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Main Content */}
        <div className="pl-64 flex flex-col min-h-screen">
          <header className="sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 h-16 flex items-center justify-between px-8">
            <div className="flex items-center text-sm font-medium text-gray-500 dark:text-gray-400">
              {/* Optional Breadcrumb or Page Title could go here */}
            </div>
            <div className="flex items-center space-x-4">
              {/* User Profile or Actions could go here */}
            </div>
          </header>

          <main className="flex-1 px-8 py-8">
            <div className="max-w-7xl mx-auto">
              <Outlet />
            </div>
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
                Teacher login
              </Link>
              <Link
                to="/join"
                className="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
              >
                Join as student
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
