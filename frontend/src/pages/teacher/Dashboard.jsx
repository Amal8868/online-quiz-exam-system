import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
    ClipboardDocumentCheckIcon,
    UserGroupIcon,
    TrashIcon,
    EyeIcon,
    CalendarDaysIcon,
    ClockIcon,
    UserCircleIcon
} from '@heroicons/react/24/outline';
import { teacherAPI } from '../../services/api';
import ConfirmationModal from '../../components/ConfirmationModal';
import AlertModal from '../../components/AlertModal';

const Dashboard = () => {
    const [stats, setStats] = useState(null);
    const [quizzes, setQuizzes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [currentTime, setCurrentTime] = useState(new Date());

    // Modal State
    const [deleteModal, setDeleteModal] = useState({ isOpen: false, quizId: null, quizTitle: '' });
    const [alertConfig, setAlertConfig] = useState({ isOpen: false, title: '', message: '', type: 'success' });

    const user = JSON.parse(localStorage.getItem('user') || sessionStorage.getItem('user') || '{}');

    useEffect(() => {
        fetchDashboardData();
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

    const formatTime = (date) => {
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
    };

    const fetchDashboardData = async () => {
        try {
            const [statsRes, quizzesRes] = await Promise.all([
                teacherAPI.getDashboardStats(),
                teacherAPI.getQuizzes()
            ]);

            setStats(statsRes.data.data.stats);
            setQuizzes(quizzesRes.data.data);
        } catch (error) {
            console.error('Error fetching dashboard data:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleDeleteQuiz = (quiz) => {
        setDeleteModal({
            isOpen: true,
            quizId: quiz.id,
            quizTitle: quiz.title
        });
    };

    const confirmDelete = async () => {
        const id = deleteModal.quizId;
        setDeleteModal({ ...deleteModal, isOpen: false });

        try {
            await teacherAPI.deleteQuiz(id);
            setAlertConfig({
                isOpen: true,
                title: 'Quiz Deleted',
                message: 'The quiz has been permanently removed.',
                type: 'success'
            });
            fetchDashboardData();
        } catch (error) {
            console.error('Error deleting quiz:', error);
            setAlertConfig({
                isOpen: true,
                title: 'Deletion Failed',
                message: error.response?.data?.message || 'Failed to delete the quiz. Please try again.',
                type: 'error'
            });
        }
    };

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center h-64 space-y-4">
                <div className="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                <p className="text-gray-500 animate-pulse font-medium">Loading your dashboard...</p>
            </div>
        );
    }

    return (
        <div className="space-y-10">
            {/* Header with Welcome and Time */}
            <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-4">
                <div>
                    <div className="flex items-center gap-4 mb-2">
                        <div className="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl border border-indigo-100 dark:border-indigo-500/20 shadow-sm">
                            <UserCircleIcon className="h-11 w-11 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <h1 className="text-5xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
                            Welcome, <span className="text-indigo-600">{user.name?.split(' ')[0] || 'Teacher'}</span>
                        </h1>
                    </div>
                    <p className="text-slate-500 font-semibold uppercase tracking-widest text-[11px] mt-2">
                        DASHBOARD & PERFORMANCE OVERVIEW
                    </p>
                    <div className="mt-8 flex flex-wrap items-center gap-8">
                        <div className="flex items-center text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 px-4 py-2 rounded-xl shadow-sm transition-all hover:shadow-md">
                            <CalendarDaysIcon className="h-5 w-5 mr-3 text-indigo-500" />
                            <span className="text-sm font-bold tracking-tight uppercase">{formatDate(currentTime)}</span>
                        </div>
                        <div className="flex items-center text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 px-4 py-2 rounded-xl shadow-sm transition-all hover:shadow-md">
                            <ClockIcon className="h-5 w-5 mr-3 text-indigo-500" />
                            <span className="text-sm font-mono font-black tracking-tight">{formatTime(currentTime)}</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                {[
                    { name: 'Total Quizzes', value: stats?.total_quizzes || 0, icon: ClipboardDocumentCheckIcon, color: 'text-purple-600', bg: 'bg-purple-50', link: '/teacher/results' },
                    { name: 'Active Now', value: stats?.active_quizzes || 0, icon: ClipboardDocumentCheckIcon, color: 'text-green-600', bg: 'bg-green-50', link: '/teacher/results' },
                    { name: 'Total Students', value: stats?.total_students || 0, icon: UserGroupIcon, color: 'text-blue-600', bg: 'bg-blue-50', link: '/teacher/classes' },
                ].map((item, i) => (
                    <motion.div
                        key={item.name}
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: i * 0.1 }}
                    >
                        <Link
                            to={item.link}
                            className="block bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 transition-all hover:border-indigo-500 hover:shadow-md group"
                        >
                            <div className="flex items-center space-x-4">
                                <div className={`p-3 ${item.bg} dark:bg-gray-700 rounded-lg group-hover:scale-110 transition-transform`}>
                                    <item.icon className={`h-6 w-6 ${item.color}`} />
                                </div>
                                <div>
                                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{item.name}</p>
                                    <p className="text-2xl font-bold text-slate-900 dark:text-white leading-none">{item.value}</p>
                                </div>
                            </div>
                        </Link>
                    </motion.div>
                ))}
            </div>

            {/* Quizzes Section */}
            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div className="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <h2 className="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        Your Quizzes
                        <span className="ml-4 px-3 py-1 bg-gray-50 dark:bg-gray-900/40 text-gray-600 dark:text-gray-400 text-xs font-semibold rounded-full border border-gray-200 dark:border-gray-700">{quizzes.length} Total</span>
                    </h2>
                </div>

                <div className="p-4">
                    {quizzes.length === 0 ? (
                        <div className="py-12 text-center opacity-50">
                            <ClipboardDocumentCheckIcon className="h-12 w-12 mx-auto text-gray-300 mb-4" />
                            <p className="text-gray-500 font-medium tracking-wide">No quizzes created yet</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-3">
                            {quizzes.map((quiz, index) => (
                                <motion.div
                                    key={quiz.id}
                                    initial={{ opacity: 0, x: -10 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    transition={{ delay: index * 0.05 }}
                                    className="group flex flex-col md:flex-row md:items-center justify-between p-4 bg-white dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg hover:border-indigo-500 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all gap-4"
                                >
                                    <div className="flex items-center space-x-4">
                                        <div className="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 transition-colors">
                                            <ClipboardDocumentCheckIcon className="h-6 w-6 text-gray-400 group-hover:text-indigo-600" />
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-0.5 group-hover:text-indigo-600 transition-colors">
                                                {quiz.title}
                                            </h3>
                                            <div className="flex flex-wrap items-center gap-3">
                                                <span className="flex items-center text-[11px] font-bold text-indigo-600 uppercase tracking-wide">
                                                    Code: {quiz.room_code}
                                                </span>
                                                <span className="text-[11px] font-medium text-gray-400 uppercase tracking-wide border-l border-gray-200 dark:border-gray-600 pl-3">
                                                    {quiz.question_count} Questions
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <Link
                                            to={`/teacher/quiz/${quiz.id}`}
                                            className="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 hover:border-indigo-500 rounded-lg text-sm font-bold text-slate-700 dark:text-gray-300 transition-all"
                                        >
                                            <EyeIcon className="h-4 w-4 mr-2 text-indigo-500" />
                                            Manage
                                        </Link>
                                        <button
                                            onClick={() => handleDeleteQuiz(quiz)}
                                            className="p-2 text-gray-400 hover:text-red-600 bg-gray-50 dark:bg-gray-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all"
                                        >
                                            <TrashIcon className="h-5 w-5" />
                                        </button>
                                    </div>
                                </motion.div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
            {/* Modals */}
            <ConfirmationModal
                isOpen={deleteModal.isOpen}
                onClose={() => setDeleteModal({ ...deleteModal, isOpen: false })}
                onConfirm={confirmDelete}
                title="Delete Quiz"
                message={`Are you sure you want to delete "${deleteModal.quizTitle}"? This action cannot be undone.`}
                confirmText="Delete Now"
                isDangerous={true}
            />

            <AlertModal
                isOpen={alertConfig.isOpen}
                onClose={() => setAlertConfig({ ...alertConfig, isOpen: false })}
                title={alertConfig.title}
                message={alertConfig.message}
                type={alertConfig.type}
            />
        </div>
    );
};

export default Dashboard;
