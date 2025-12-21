import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
    PlusIcon,
    ClipboardDocumentCheckIcon,
    UserGroupIcon,
    TrashIcon,
    EyeIcon
} from '@heroicons/react/24/outline';
import { teacherAPI } from '../../services/api';

const Dashboard = () => {
    const [stats, setStats] = useState(null);
    const [quizzes, setQuizzes] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchDashboardData();
    }, []);

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

    const handleDeleteQuiz = async (id) => {
        if (window.confirm('Are you sure you want to delete this quiz?')) {
            try {
                // await teacherAPI.deleteQuiz(id); // Not implemented in backend yet
                alert("Delete not implemented in this demo.");
                fetchDashboardData();
            } catch (error) {
                console.error('Error deleting quiz:', error);
            }
        }
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                <Link to="/teacher/create-quiz" className="flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm">
                    <PlusIcon className="h-5 w-5 mr-2" />
                    Create Quiz
                </Link>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
                {[
                    { name: 'Total Quizzes', value: stats?.total_quizzes || 0, icon: ClipboardDocumentCheckIcon, color: 'bg-purple-500' },
                    { name: 'Active Quizzes', value: stats?.active_quizzes || 0, icon: ClipboardDocumentCheckIcon, color: 'bg-green-500' },
                    { name: 'Total Students', value: stats?.total_students || 0, icon: UserGroupIcon, color: 'bg-blue-500' },
                ].map((item) => (
                    <motion.div
                        key={item.name}
                        className="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                        whileHover={{ y: -5 }}
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                    >
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className={`flex-shrink-0 rounded-md p-3 ${item.color}`}>
                                    <item.icon className="h-6 w-6 text-white" aria-hidden="true" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{item.name}</dt>
                                        <dd>
                                            <div className="text-lg font-medium text-gray-900 dark:text-white">{item.value}</div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </motion.div>
                ))}
            </div>

            <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-white mb-4">Your Quizzes</h2>
                {quizzes.length === 0 ? (
                    <p className="text-gray-500 dark:text-gray-400">No quizzes created yet.</p>
                ) : (
                    <div className="flow-root">
                        <ul className="-my-5 divide-y divide-gray-200 dark:divide-gray-700">
                            {quizzes.map((quiz) => (
                                <li key={quiz.id} className="py-4">
                                    <div className="flex items-center space-x-4">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                {quiz.title}
                                            </p>
                                            <p className="text-sm text-gray-500 dark:text-gray-400 truncate">
                                                Code: <span className="font-mono font-bold text-indigo-600">{quiz.room_code}</span> • {quiz.question_count} Questions
                                            </p>
                                        </div>
                                        <div className="flex space-x-2">
                                            <Link
                                                to={`/teacher/quiz/${quiz.id}`}
                                                className="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50"
                                            >
                                                <EyeIcon className="h-4 w-4 mr-1" />
                                                Manage
                                            </Link>
                                            <button
                                                onClick={() => handleDeleteQuiz(quiz.id)}
                                                className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200"
                                            >
                                                <TrashIcon className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
};

export default Dashboard;
