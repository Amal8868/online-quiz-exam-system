import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    PlusIcon,
    UserGroupIcon,
    AcademicCapIcon,
    MagnifyingGlassIcon,
    ChevronRightIcon,
    TableCellsIcon,
    PresentationChartBarIcon
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
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <div className="flex items-center gap-4 mb-2">
                        <div className="p-2.5 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl">
                            <UserGroupIcon className="h-10 w-10 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <h1 className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Your Classes</h1>
                    </div>
                    <p className="text-slate-500 font-semibold uppercase tracking-widest text-[11px] mt-2">
                        MANAGE YOUR CLASS ROSTERS AND STUDENT GROUPS
                    </p>
                </div>
            </div>

            {/* Search and Actions Row */}
            <div className="flex flex-col md:flex-row items-center justify-between gap-4 mb-8">
                <div className="relative group max-w-md w-full">
                    <MagnifyingGlassIcon className="h-5 w-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-indigo-500 transition-colors" />
                    <input
                        type="text"
                        placeholder="Search classes or sections..."
                        className="w-full pl-11 pr-4 py-2.5 bg-white dark:bg-gray-800 border-2 border-gray-100 dark:border-gray-700 rounded-xl focus:border-indigo-500 outline-none transition-all font-medium text-base shadow-sm"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                    />
                </div>

                <div className="flex items-center space-x-3 w-full md:w-auto justify-end">
                    <button
                        onClick={() => navigate('/teacher/classes/import')}
                        className="btn bg-white dark:bg-gray-800 text-indigo-600 hover:text-indigo-700 font-bold px-6 py-2.5 rounded-2xl flex items-center transition-all border-2 border-gray-100 dark:border-gray-700 shadow-sm text-sm"
                    >
                        <TableCellsIcon className="h-5 w-5 mr-2" />
                        One-Time Upload
                    </button>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="btn bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-xl flex items-center transition-all shadow-lg shadow-indigo-200 dark:shadow-none text-sm"
                    >
                        <PlusIcon className="h-5 w-5 mr-2" /> New Class
                    </button>
                </div>
            </div>

            {/* Class Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {filteredClasses.length > 0 ? (
                    filteredClasses.map((item) => (
                        <motion.div
                            key={item.id}
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            className="bg-white dark:bg-[#111827] rounded-xl border border-gray-100 dark:border-slate-800 shadow-xl shadow-gray-100/50 dark:shadow-none overflow-hidden group transition-all duration-300"
                        >
                            <Link to={`/teacher/classes/${item.id}`} className="block h-full p-8">
                                <div className="flex justify-between items-start mb-6">
                                    <div className="p-3 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl">
                                        <AcademicCapIcon className="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                    <span className="px-3 py-1 bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 text-xs font-bold rounded-lg border border-transparent dark:border-slate-700/50">
                                        {item.academic_year}
                                    </span>
                                </div>

                                <h3 className="text-2xl font-bold text-slate-900 dark:text-white mb-1 group-hover:text-indigo-600 transition-colors">
                                    {item.name}
                                </h3>
                                <p className="text-gray-500 dark:text-slate-500 text-base font-medium">
                                    {item.section || 'General Section'}
                                </p>

                                <div className="mt-auto pt-6 border-t border-gray-50 dark:border-slate-800/50 flex justify-end">
                                    <div className="flex items-center text-gray-400 dark:text-slate-500 font-bold text-[10px] uppercase tracking-widest">
                                        <UserGroupIcon className="h-4 w-4 mr-2 opacity-70" />
                                        {item.student_count || 0} Students
                                    </div>
                                </div>
                            </Link>
                        </motion.div>
                    ))
                ) : (
                    <div className="col-span-full py-20 text-center bg-gray-50/50 dark:bg-gray-800/30 rounded-xl border-4 border-dashed border-gray-100 dark:border-gray-700">
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
                            className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full p-10 border border-gray-100 dark:border-gray-700"
                        >
                            <h2 className="text-3xl font-black tracking-tight mb-6">Create New Class</h2>
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
