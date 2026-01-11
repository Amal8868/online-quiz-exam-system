import React, { useState, useEffect } from 'react';
import { adminAPI } from '../../services/api';
import {
    PrinterIcon,
    XCircleIcon,
    BookOpenIcon,
    AcademicCapIcon
} from '@heroicons/react/24/outline';

const Reports = () => {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchReports = async () => {
            try {
                const res = await adminAPI.getReports();
                if (res.data.success) {
                    setStats(res.data.data);
                } else {
                    setError(res.data.message || "Unknown API error");
                }
            } catch (error) {
                console.error("Failed to fetch reports", error);
                setError(error.response?.data?.message || error.message || "Network error");
            } finally {
                setLoading(false);
            }
        };

        fetchReports();
    }, []);

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-6">
                <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 rounded-xl text-red-600 dark:text-red-400">
                    <h3 className="font-bold flex items-center gap-2 mb-1">
                        <XCircleIcon className="h-5 w-5" />
                        Something went wrong
                    </h3>
                    <p className="text-sm opacity-90">{error}</p>
                    <button
                        onClick={() => window.location.reload()}
                        className="mt-3 text-xs font-bold uppercase tracking-wider underline"
                    >
                        Try Refreshing
                    </button>
                </div>
            </div>
        );
    }

    if (!stats) {
        return (
            <div className="p-6 text-center text-gray-500">
                No report data received from server.
            </div>
        );
    }

    const { subjects_overview, usage } = stats;

    const sortedSubjects = [...(subjects_overview || [])].sort((a, b) => {
        if (a.quiz_count === 0 && b.quiz_count > 0) return -1;
        if (a.quiz_count > 0 && b.quiz_count === 0) return 1;
        return a.name.localeCompare(b.name);
    });

    const getEngagementScore = (quizCount) => {
        if (quizCount === 0) return 0;
        if (quizCount < 2) return 1;
        if (quizCount < 5) return 2;
        if (quizCount < 10) return 3;
        return 4;
    };

    const sortedClasses = [...(usage || [])].sort((a, b) => {
        const scoreA = getEngagementScore(a.quiz_count);
        const scoreB = getEngagementScore(b.quiz_count);
        if (scoreA !== scoreB) return scoreB - scoreA;
        return a.name.localeCompare(b.name);
    });

    return (
        <div className="space-y-12 p-6 print:p-0 max-w-7xl mx-auto">
            {/* Header Section */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 border-b border-gray-100 dark:border-gray-800 pb-10 print:hidden">
                <div className="space-y-1">
                    <h1 className="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">System Reports</h1>
                    <p className="text-gray-500 dark:text-gray-400 text-lg font-medium">Deep insights into your organization's performance.</p>
                </div>
                <button
                    onClick={() => window.print()}
                    className="inline-flex items-center px-6 py-3 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm text-base font-bold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all active:scale-95 group border-b-4 hover:border-b-2 hover:translate-y-[2px]"
                >
                    <PrinterIcon className="-ml-1 mr-3 h-6 w-6 text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform" aria-hidden="true" />
                    Download / Print Report
                </button>
            </div>

            {/* Print Only Header */}
            <div className="hidden print:block mb-10 text-center border-b pb-8">
                <h1 className="text-3xl font-bold text-gray-900">QuizMaster Master System Report</h1>
                <p className="text-gray-500 mt-2">Generated on {new Date().toLocaleDateString()} at {new Date().toLocaleTimeString()}</p>
            </div>

            {/* --- SECTION: SUBJECTS / COURSES OVERVIEW --- */}
            <section className="animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex items-center gap-3 mb-6">
                    <div className="p-2.5 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl">
                        <BookOpenIcon className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <h2 className="text-xl font-bold text-gray-900 dark:text-white">Subjects / Courses Overview</h2>
                </div>

                <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                            <thead className="bg-gray-50/50 dark:bg-gray-800/50">
                                <tr>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Subject Name</th>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Code</th>
                                    <th scope="col" className="px-6 py-4 text-center text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Classes Assigned</th>
                                    <th scope="col" className="px-6 py-4 text-center text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Quizzes</th>
                                    <th scope="col" className="px-6 py-4 text-center text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Avg Performance</th>
                                    <th scope="col" className="px-6 py-4 text-right text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Status / Alert</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700/50">
                                {sortedSubjects.map((subject) => (
                                    <tr key={subject.id} className={`hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-all group ${subject.quiz_count === 0 ? 'bg-red-50/30 dark:bg-red-900/10' : ''}`}>
                                        <td className="px-6 py-5 whitespace-nowrap">
                                            <div className="font-bold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                                {subject.name}
                                            </div>
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap">
                                            <span className="px-2.5 py-1 rounded-lg bg-gray-100/80 dark:bg-gray-700 text-[11px] font-mono font-bold text-gray-600 dark:text-gray-400 border border-gray-200/50 dark:border-gray-600">
                                                {subject.code}
                                            </span>
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-center text-gray-600 dark:text-gray-400 font-medium font-mono">
                                            {subject.classes_assigned}
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-center text-gray-600 dark:text-gray-400 font-medium font-mono">
                                            {subject.quiz_count}
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-center">
                                            {subject.quiz_count > 0 ? (
                                                <div className="flex flex-col items-center gap-1">
                                                    <span className={`text-sm font-black ${subject.avg_score < 50 ? 'text-red-600 dark:text-red-400' :
                                                        subject.avg_score < 75 ? 'text-amber-600 dark:text-amber-400' :
                                                            'text-emerald-600 dark:text-emerald-400'
                                                        }`}>
                                                        {subject.avg_score !== null ? `${subject.avg_score}%` : 'N/A'}
                                                    </span>
                                                    {subject.avg_score !== null && (
                                                        <div className="w-16 bg-gray-100 dark:bg-gray-700 rounded-full h-1 overflow-hidden">
                                                            <div
                                                                className={`h-full ${subject.avg_score < 50 ? 'bg-red-500' :
                                                                    subject.avg_score < 75 ? 'bg-amber-500' :
                                                                        'bg-emerald-500'
                                                                    }`}
                                                                style={{ width: `${subject.avg_score}%` }}
                                                            ></div>
                                                        </div>
                                                    )}
                                                </div>
                                            ) : (
                                                <div className="flex flex-col items-center">
                                                    <span className="text-red-400 text-xs font-bold flex items-center gap-1">
                                                        <XCircleIcon className="h-3 w-3" />
                                                        Missing Data
                                                    </span>
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-right">
                                            <span className={`text-xs font-black uppercase tracking-widest ${subject.quiz_count > 0
                                                ? (subject.avg_score < 50 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400')
                                                : 'text-red-600 dark:text-red-400'
                                                }`}>
                                                {subject.quiz_count > 0
                                                    ? (subject.avg_score < 50 ? 'Struggling' : 'Active')
                                                    : 'No Quizzes'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {/* --- SECTION: TOP ACTIVE CLASSES --- */}
            <section className="animate-in fade-in slide-in-from-bottom-4 duration-700 delay-150">
                <div className="flex items-center gap-3 mb-6">
                    <div className="p-2.5 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl">
                        <AcademicCapIcon className="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <h2 className="text-xl font-bold text-gray-900 dark:text-white">Top Active Classes</h2>
                </div>

                <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                            <thead className="bg-gray-50/50 dark:bg-gray-800/50">
                                <tr>
                                    <th scope="col" className="px-6 py-4 text-left text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Class Name</th>
                                    <th scope="col" className="px-6 py-4 text-center text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Students</th>
                                    <th scope="col" className="px-6 py-4 text-center text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Quizzes</th>
                                    <th scope="col" className="px-6 py-4 text-right text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Engagement Level</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700/50">
                                {sortedClasses.map((cls) => (
                                    <tr key={cls.id} className={`hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-all group ${cls.quiz_count === 0 ? 'bg-amber-50/20 dark:bg-amber-900/10' : ''}`}>
                                        <td className="px-6 py-5 whitespace-nowrap">
                                            <div className="flex flex-col">
                                                <span className="font-bold text-gray-900 dark:text-gray-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors uppercase tracking-tight">{cls.name}</span>
                                                <span className="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-tighter">SECTION {cls.section || '---'}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-center text-gray-600 dark:text-gray-400 font-bold font-mono">
                                            {cls.student_count}
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-center text-gray-600 dark:text-gray-400 font-bold font-mono">
                                            {cls.quiz_count}
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-right">
                                            <span className={`text-[10px] font-black uppercase tracking-widest ${cls.quiz_count >= 10
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : cls.quiz_count >= 5
                                                    ? 'text-blue-600 dark:text-blue-400'
                                                    : cls.quiz_count >= 2
                                                        ? 'text-amber-600 dark:text-amber-400'
                                                        : cls.quiz_count >= 1
                                                            ? 'text-orange-600 dark:text-orange-400'
                                                            : 'text-gray-500 dark:text-gray-400'
                                                }`}>
                                                {cls.quiz_count >= 10 ? 'High Intensity' :
                                                    cls.quiz_count >= 5 ? 'Medium Engagement' :
                                                        cls.quiz_count >= 2 ? 'Low Frequency' :
                                                            cls.quiz_count >= 1 ? 'Needs Attention' :
                                                                'Zero Activity'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    );
};

export default Reports;
