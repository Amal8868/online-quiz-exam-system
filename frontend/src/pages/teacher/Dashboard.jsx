import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
    PlusIcon,
    AcademicCapIcon,
    ClipboardDocumentCheckIcon,
    UserGroupIcon,
    TrashIcon
} from '@heroicons/react/24/outline';
import { teacherAPI } from '../../services/api';

const Dashboard = () => {
    const [stats, setStats] = useState(null);
    const [classes, setClasses] = useState([]);
    const [quizzes, setQuizzes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreateClassModal, setShowCreateClassModal] = useState(false);
    const [newClass, setNewClass] = useState({ name: '', section: '', academic_year: '' });

    useEffect(() => {
        fetchDashboardData();
    }, []);

    const fetchDashboardData = async () => {
        try {
            const [statsRes, classesRes, quizzesRes] = await Promise.all([
                teacherAPI.getDashboardStats(),
                teacherAPI.getClasses(),
                teacherAPI.getQuizzes()
            ]);

            setStats(statsRes.data.data);
            setClasses(classesRes.data.data);
            setQuizzes(quizzesRes.data.data);
        } catch (error) {
            console.error('Error fetching dashboard data:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleCreateClass = async (e) => {
        e.preventDefault();
        try {
            await teacherAPI.createClass(newClass);
            setShowCreateClassModal(false);
            setNewClass({ name: '', section: '', academic_year: '' });
            fetchDashboardData();
        } catch (error) {
            console.error('Error creating class:', error);
        }
    };

    const handleDeleteQuiz = async (id) => {
        if (window.confirm('Are you sure you want to delete this quiz?')) {
            try {
                await teacherAPI.deleteQuiz(id);
                fetchDashboardData();
            } catch (error) {
                console.error('Error deleting quiz:', error);
            }
        }
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                <div className="flex space-x-3">
                    <button
                        onClick={() => setShowCreateClassModal(true)}
                        className="btn btn-outline"
                    >
                        <PlusIcon className="h-5 w-5" />
                        Create Class
                    </button>
                    <Link to="/teacher/create-quiz" className="btn btn-primary">
                        <PlusIcon className="h-5 w-5" />
                        Create Quiz
                    </Link>
                </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    { name: 'Total Classes', value: stats?.total_classes || 0, icon: AcademicCapIcon, color: 'bg-blue-500' },
                    { name: 'Total Students', value: stats?.total_students || 0, icon: UserGroupIcon, color: 'bg-green-500' },
                    { name: 'Active Quizzes', value: stats?.active_quizzes || 0, icon: ClipboardDocumentCheckIcon, color: 'bg-purple-500' },
                    { name: 'Completed Exams', value: stats?.completed_exams || 0, icon: ClipboardDocumentCheckIcon, color: 'bg-yellow-500' },
                ].map((item) => (
                    <motion.div
                        key={item.name}
                        className="card overflow-hidden"
                        whileHover={{ y: -5 }}
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                    >
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
                    </motion.div>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Recent Quizzes */}
                <div className="card">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-white mb-4">Recent Quizzes</h2>
                    {quizzes.length === 0 ? (
                        <p className="text-gray-500 dark:text-gray-400">No quizzes created yet.</p>
                    ) : (
                        <div className="flow-root">
                            <ul className="-my-5 divide-y divide-gray-200 dark:divide-gray-700">
                                {quizzes.slice(0, 5).map((quiz) => (
                                    <li key={quiz.id} className="py-4">
                                        <div className="flex items-center space-x-4">
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                    {quiz.title}
                                                </p>
                                                <p className="text-sm text-gray-500 dark:text-gray-400 truncate">
                                                    Code: <span className="font-mono font-bold">{quiz.room_code}</span> • {quiz.question_count} Questions
                                                </p>
                                            </div>
                                            <div className="flex space-x-2">
                                                <Link
                                                    to={`/teacher/quiz/${quiz.id}/results`}
                                                    className="inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none"
                                                >
                                                    Results
                                                </Link>
                                                <button
                                                    onClick={() => handleDeleteQuiz(quiz.id)}
                                                    className="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none"
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

                {/* Classes */}
                <div className="card">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-white mb-4">Your Classes</h2>
                    {classes.length === 0 ? (
                        <p className="text-gray-500 dark:text-gray-400">No classes created yet.</p>
                    ) : (
                        <div className="flow-root">
                            <ul className="-my-5 divide-y divide-gray-200 dark:divide-gray-700">
                                {classes.slice(0, 5).map((cls) => (
                                    <li key={cls.id} className="py-4">
                                        <div className="flex items-center space-x-4">
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                    {cls.name}
                                                </p>
                                                <p className="text-sm text-gray-500 dark:text-gray-400 truncate">
                                                    {cls.section} • {cls.academic_year}
                                                </p>
                                            </div>
                                            <div>
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    Active
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            {/* Create Class Modal */}
            {showCreateClassModal && (
                <div className="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onClick={() => setShowCreateClassModal(false)}></div>
                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div className="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form onSubmit={handleCreateClass}>
                                <div className="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                        Create New Class
                                    </h3>
                                    <div className="mt-4 space-y-4">
                                        <div>
                                            <label htmlFor="className" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Class Name</label>
                                            <input
                                                type="text"
                                                name="name"
                                                id="className"
                                                required
                                                className="input mt-1"
                                                placeholder="e.g. Mathematics 101"
                                                value={newClass.name}
                                                onChange={(e) => setNewClass({ ...newClass, name: e.target.value })}
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="section" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Section</label>
                                            <input
                                                type="text"
                                                name="section"
                                                id="section"
                                                className="input mt-1"
                                                placeholder="e.g. Section A"
                                                value={newClass.section}
                                                onChange={(e) => setNewClass({ ...newClass, section: e.target.value })}
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="year" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Academic Year</label>
                                            <input
                                                type="text"
                                                name="academic_year"
                                                id="year"
                                                required
                                                className="input mt-1"
                                                placeholder="e.g. 2023-2024"
                                                value={newClass.academic_year}
                                                onChange={(e) => setNewClass({ ...newClass, academic_year: e.target.value })}
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <button type="submit" className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                                        Create
                                    </button>
                                    <button type="button" onClick={() => setShowCreateClassModal(false)} className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Dashboard;
