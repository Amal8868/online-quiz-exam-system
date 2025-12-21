import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    PlusIcon,
    UserGroupIcon,
    AcademicCapIcon,
    MagnifyingGlassIcon,
    ChevronRightIcon,
    TableCellsIcon
} from '@heroicons/react/24/outline';
import { teacherAPI } from '../../services/api';
import { Link, useNavigate } from 'react-router-dom';

const ClassList = () => {
    const navigate = useNavigate();
    const [classes, setClasses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [newClass, setNewClass] = useState({
        name: '',
        section: '',
        academic_year: new Date().getFullYear() + '-' + (new Date().getFullYear() + 1)
    });
    const [searchQuery, setSearchQuery] = useState('');

    useEffect(() => {
        fetchClasses();
    }, []);

    const fetchClasses = async () => {
        try {
            const res = await teacherAPI.getClasses();
            setClasses(res.data.data || []);
        } catch (error) {
            console.error('Error fetching classes:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleCreateClass = async (e) => {
        e.preventDefault();
        try {
            await teacherAPI.createClass(newClass);
            setShowCreateModal(false);
            setNewClass({
                name: '',
                section: '',
                academic_year: new Date().getFullYear() + '-' + (new Date().getFullYear() + 1)
            });
            fetchClasses();
        } catch (error) {
            alert('Failed to create class: ' + (error.response?.data?.message || error.message));
        }
    };

    const filteredClasses = classes.filter(c =>
        c.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        c.section.toLowerCase().includes(searchQuery.toLowerCase())
    );

    if (loading) return <div className="flex justify-center items-center h-screen">Loading Classes...</div>;

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div className="flex justify-between items-center mb-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Your Classes</h1>
                    <p className="text-gray-500 text-sm">Manage your class rosters and student groups</p>
                </div>
                <div className="flex space-x-3">
                    <button
                        onClick={() => navigate('/teacher/classes/import')}
                        className="btn bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 font-bold px-4 py-2 rounded-xl flex items-center transition-all border border-indigo-100 dark:border-indigo-800"
                    >
                        <TableCellsIcon className="h-5 w-5 mr-1" />
                        One-Time Upload
                    </button>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="btn btn-primary flex items-center"
                    >
                        <PlusIcon className="h-5 w-5 mr-2" /> New Class
                    </button>
                </div>
            </div>

            {/* Search and Filters */}
            <div className="mb-6 relative">
                <MagnifyingGlassIcon className="h-5 w-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                    type="text"
                    placeholder="Search classes or sections..."
                    className="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                />
            </div>

            {/* Class Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {filteredClasses.length > 0 ? (
                    filteredClasses.map((item) => (
                        <motion.div
                            key={item.id}
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            whileHover={{ y: -4 }}
                            className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden"
                        >
                            <Link to={`/teacher/classes/${item.id}`} className="block p-6">
                                <div className="flex justify-between items-start mb-4">
                                    <div className="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                                        <AcademicCapIcon className="h-6 w-6 text-indigo-600" />
                                    </div>
                                    <span className="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[10px] font-bold rounded uppercase">
                                        {item.academic_year}
                                    </span>
                                </div>
                                <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-1">
                                    {item.name}
                                </h3>
                                <p className="text-gray-500 text-sm mb-4">
                                    Section: {item.section || 'N/A'}
                                </p>
                                <div className="flex items-center space-x-4 pt-4 border-t border-gray-50 dark:border-gray-700">
                                    <div className="flex items-center text-gray-500 text-xs font-medium">
                                        <UserGroupIcon className="h-4 w-4 mr-1" />
                                        {item.student_count || 0} Students
                                    </div>
                                    <div className="flex items-center text-gray-500 text-xs font-medium">
                                        <ChevronRightIcon className="h-4 w-4 mr-1" />
                                        {item.quiz_count || 0} Quizzes
                                    </div>
                                </div>
                            </Link>
                        </motion.div>
                    ))
                ) : (
                    <div className="col-span-full py-12 text-center bg-gray-50 dark:bg-gray-800/50 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700">
                        <UserGroupIcon className="h-12 w-12 mx-auto text-gray-300 mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white">No classes found</h3>
                        <p className="text-gray-500 mt-1">Create your first class to start managing students</p>
                    </div>
                )}
            </div>

            {/* Create Modal */}
            <AnimatePresence>
                {showCreateModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/50 backdrop-blur-sm">
                        <motion.div
                            initial={{ scale: 0.9, opacity: 0 }}
                            animate={{ scale: 1, opacity: 1 }}
                            exit={{ scale: 0.9, opacity: 0 }}
                            className="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6"
                        >
                            <h2 className="text-xl font-bold mb-4">Create New Class</h2>
                            <form onSubmit={handleCreateClass} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Class Name *</label>
                                    <input
                                        type="text"
                                        required
                                        placeholder="e.g., Computer Science 101"
                                        className="input w-full"
                                        value={newClass.name}
                                        onChange={e => setNewClass({ ...newClass, name: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Section / Group</label>
                                    <input
                                        type="text"
                                        placeholder="e.g., Section A"
                                        className="input w-full"
                                        value={newClass.section}
                                        onChange={e => setNewClass({ ...newClass, section: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Academic Year</label>
                                    <input
                                        type="text"
                                        required
                                        placeholder="e.g., 2023-2024"
                                        className="input w-full"
                                        value={newClass.academic_year}
                                        onChange={e => setNewClass({ ...newClass, academic_year: e.target.value })}
                                    />
                                </div>
                                <div className="flex space-x-3 pt-4">
                                    <button
                                        type="button"
                                        onClick={() => setShowCreateModal(false)}
                                        className="btn btn-secondary flex-1"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        className="btn btn-primary flex-1"
                                    >
                                        Create Class
                                    </button>
                                </div>
                            </form>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>
        </div>
    );
};

export default ClassList;
