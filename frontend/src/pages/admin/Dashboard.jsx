import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { adminAPI, authAPI } from '../../services/api';
import { CalendarDaysIcon, ClockIcon } from '@heroicons/react/24/outline';

const AdminDashboard = () => {
    const [stats, setStats] = useState({ users: 0, classes: 0 });

    const [loading, setLoading] = useState(true);
    const [currentTime, setCurrentTime] = useState(new Date());

    const [user, setUser] = useState(JSON.parse(
        (localStorage.getItem('token') ? localStorage.getItem('user') : sessionStorage.getItem('user')) ||
        localStorage.getItem('user') ||
        sessionStorage.getItem('user') ||
        '{}'
    ));

    useEffect(() => {
        const timer = setInterval(() => setCurrentTime(new Date()), 1000);
        return () => clearInterval(timer);
    }, []);

    const formatDate = (date) => {
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };

    useEffect(() => {
        const fetchStats = async () => {
            // Refresh user data from server to ensure sync
            authAPI.getCurrentUser().then(res => {
                if (res.data.success && res.data.data.user) {
                    const refreshedUser = res.data.data.user;
                    setUser(refreshedUser);
                    const storage = localStorage.getItem('token') ? localStorage : sessionStorage;
                    storage.setItem('user', JSON.stringify(refreshedUser));
                }
            }).catch(err => { });

            try {
                const response = await adminAPI.getStats();
                if (response.data.success) {
                    setStats(response.data.data);
                }
            } catch (error) {
                console.error("Failed to fetch admin stats", error);
            } finally {
                setLoading(false);
            }
        };

        fetchStats();
    }, []);

    return (
        <div className="space-y-6">
            {/* Standard Profile Card */}
            <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                <div className="flex flex-col md:flex-row items-center justify-between gap-6">
                    {/* Left: User Info */}
                    <div className="flex flex-col md:flex-row items-center gap-6 text-center md:text-left">
                        <div className="relative">
                            <img
                                src={user.profile_pic || (user.gender && user.gender.toLowerCase() === 'female'
                                    ? `https://api.dicebear.com/9.x/avataaars/svg?seed=${encodeURIComponent(user.username || user.name)}&top=hijab&clothing=blazerAndShirt`
                                    : `https://avatar.iran.liara.run/public/boy?username=${user.username || user.name}`)}
                                alt="Profile"
                                className="h-24 w-24 rounded-full border-4 border-white dark:border-gray-700 shadow-md object-cover"
                            />
                            <div className="absolute bottom-1 right-1 h-6 w-6 bg-green-500 border-4 border-white dark:border-slate-900 rounded-full"></div>
                        </div>

                        <div className="space-y-2">
                            <div>
                                <h1 className="text-3xl font-black text-gray-900 dark:text-white tracking-tight mb-1">
                                    {user.name || 'Administrator'}
                                </h1>
                                <div className="flex items-center justify-center md:justify-start gap-2 text-indigo-600 dark:text-indigo-200 font-medium">
                                    <span className="px-3 py-1 bg-indigo-50 dark:bg-white/10 rounded-full text-xs font-bold uppercase tracking-wider backdrop-blur-sm border border-indigo-100 dark:border-white/10 text-indigo-700 dark:text-indigo-200">
                                        Super Admin
                                    </span>
                                </div>
                            </div>

                            <div className="flex flex-col gap-1 text-gray-500 dark:text-slate-400 text-sm font-medium">
                                <div className="flex items-center justify-center md:justify-start gap-2">
                                    <ClockIcon className="h-4 w-4" />
                                    <span>System Active since 2024</span>
                                </div>
                                <div className="flex items-center justify-center md:justify-start gap-2">
                                    <CalendarDaysIcon className="h-4 w-4" />
                                    <span>{formatDate(currentTime)}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right: Date and Time Display */}
                    <div className="w-full md:w-auto text-center md:text-right p-4">
                        <div className="inline-flex flex-col items-center md:items-end">
                            <h2 className="text-3xl font-black text-gray-900 dark:text-white tracking-tight leading-none font-sans drop-shadow-sm">
                                {currentTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true })}
                            </h2>
                            <p className="mt-2 text-indigo-600 dark:text-indigo-200 font-bold text-sm uppercase tracking-widest flex items-center gap-2">
                                <CalendarDaysIcon className="h-4 w-4" />
                                {formatDate(currentTime)}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {/* User Stats */}
                <div className="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-white mb-2">Users</h2>
                    <p className="text-3xl font-bold text-primary-600">
                        {loading ? '...' : stats.users}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Total Registered Users</p>
                </div>

                {/* Class Stats */}
                <div className="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-white mb-2">Classes</h2>
                    <p className="text-3xl font-bold text-primary-600">
                        {loading ? '...' : stats.classes}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Total Classes</p>
                </div>

                <div className="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-white mb-2">System Status</h2>
                    <div className="flex items-center mt-2">
                        <span className="h-3 w-3 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                        <span className="text-gray-700 dark:text-gray-300">Active</span>
                    </div>
                </div>
            </div>

            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-4">Welcome, Admin</h3>
                <p className="text-gray-600 dark:text-gray-300">
                    Use the sidebar to manage Users (Teachers, Students) and Classes.
                    You can create new accounts, assign teachers to classes, and more.
                </p>
            </div>
        </div>
    );
};

export default AdminDashboard;
