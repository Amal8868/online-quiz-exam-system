import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
    ArrowLeftIcon,
    ArrowUpTrayIcon,
    UserIcon,
    MagnifyingGlassIcon,
    TrashIcon
} from '@heroicons/react/24/outline';
import { teacherAPI } from '../../services/api';

const ClassDetails = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const [classInfo, setClassInfo] = useState(null);
    const [students, setStudents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchQuery, setSearchQuery] = useState('');
    const [uploading, setUploading] = useState(false);

    const fetchDetails = useCallback(async () => {
        try {
            const res = await teacherAPI.getClassDetails(id);
            setClassInfo(res.data.data.class);
            setStudents(res.data.data.students || []);
        } catch (error) {
            console.error('Error fetching class details:', error);
            alert('Failed to load class details');
            navigate('/teacher/classes');
        } finally {
            setLoading(false);
        }
    }, [id, navigate]);

    useEffect(() => {
        fetchDetails();
    }, [fetchDetails]);

    const handleFileUpload = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        setUploading(true);
        const reader = new FileReader();
        reader.onload = async (event) => {
            try {
                const text = event.target.result;
                const rows = text.split('\n');
                const parsedStudents = [];

                rows.forEach((row, index) => {
                    const cols = row.split(',').map(c => c.trim());
                    if (cols.length >= 2 && index > 0) { // Skip header
                        const [studentId, name] = cols;
                        if (studentId && name) {
                            parsedStudents.push({ student_id: studentId, name: name });
                        }
                    }
                });

                if (parsedStudents.length === 0) {
                    alert('No valid student data found in CSV. Format: student_id,name');
                    return;
                }

                await teacherAPI.uploadClassStudents(id, parsedStudents);
                alert('Students uploaded successfully!');
                fetchDetails();
            } catch (error) {
                alert('Upload failed: ' + (error.response?.data?.message || error.message));
            } finally {
                setUploading(false);
            }
        };
        reader.readAsText(file);
    };

    const filteredStudents = students.filter(s =>
        s.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        s.student_id.toLowerCase().includes(searchQuery.toLowerCase())
    );

    if (loading) return <div className="flex justify-center items-center h-screen">Loading Class Details...</div>;

    return (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <button
                onClick={() => navigate('/teacher/classes')}
                className="flex items-center text-gray-500 hover:text-gray-900 mb-6 transition-colors"
            >
                <ArrowLeftIcon className="h-4 w-4 mr-2" /> Back to Classes
            </button>

            <div className="bg-white dark:bg-gray-800 rounded-2xl p-8 border border-gray-100 dark:border-gray-700 shadow-sm mb-8">
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">{classInfo?.name}</h1>
                        <div className="flex items-center mt-2 space-x-4">
                            <span className="text-indigo-600 font-bold uppercase text-[10px] tracking-wider px-2 py-1 bg-indigo-50 rounded">
                                {classInfo?.academic_year}
                            </span>
                            <span className="text-gray-400 font-medium text-sm">
                                {classInfo?.section || 'No Section'}
                            </span>
                        </div>
                    </div>
                    <div className="relative">
                        <input
                            type="file"
                            accept=".csv"
                            onChange={handleFileUpload}
                            className="hidden"
                            id="csv-upload"
                            disabled={uploading}
                        />
                        <label
                            htmlFor="csv-upload"
                            className={`btn btn-primary flex items-center cursor-pointer ${uploading ? 'opacity-50 pointer-events-none' : ''}`}
                        >
                            <ArrowUpTrayIcon className="h-5 w-5 mr-2" />
                            {uploading ? 'Uploading...' : 'Import Roster (CSV)'}
                        </label>
                    </div>
                </div>
            </div>

            <div className="bg-white dark:bg-gray-800 rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-700 shadow-sm">
                <div className="p-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/50">
                    <h2 className="font-bold">Student Roster ({students.length})</h2>
                    <div className="relative w-64">
                        <MagnifyingGlassIcon className="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search students..."
                            className="w-full pl-9 pr-4 py-1.5 text-sm rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800"
                            value={searchQuery}
                            onChange={e => setSearchQuery(e.target.value)}
                        />
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left">
                        <thead className="bg-gray-50 dark:bg-gray-800/80 text-[10px] uppercase font-bold text-gray-400">
                            <tr>
                                <th className="px-6 py-4">Student Name</th>
                                <th className="px-6 py-4">Student ID</th>
                                <th className="px-6 py-4">Quizzes Taken</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                            {filteredStudents.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center">
                                            <div className="h-8 w-8 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mr-3">
                                                <UserIcon className="h-4 w-4 text-indigo-600" />
                                            </div>
                                            <span className="font-medium">{s.name}</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 font-mono text-sm text-gray-500">
                                        {s.student_id}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded-full">
                                            {s.quiz_count || 0} Attempts
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <button className="text-gray-400 hover:text-red-600 transition-colors">
                                            <TrashIcon className="h-5 w-5" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {filteredStudents.length === 0 && (
                                <tr>
                                    <td colSpan="4" className="px-6 py-12 text-center text-gray-400 italic">
                                        No students found in this class.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default ClassDetails;
