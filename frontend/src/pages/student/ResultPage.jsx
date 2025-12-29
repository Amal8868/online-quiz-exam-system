import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
    CheckCircleIcon,
    HomeIcon,
    NoSymbolIcon
} from '@heroicons/react/24/outline';
import { studentAPI } from '../../services/api';

const ResultPage = () => {
    const { resultId } = useParams();
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchResult = async () => {
            try {
                const response = await studentAPI.getResult(resultId);
                setResult(response.data.data);
            } catch (error) {
                console.error('Error fetching result:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchResult();
    }, [resultId]);

    if (loading) return <div className="flex justify-center items-center h-screen">Loading Results...</div>;

    if (!result) return <div className="text-center mt-20">Result not found.</div>;



    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
            <div className="max-w-3xl mx-auto">
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden"
                >
                    <div className="px-4 py-5 sm:p-6 text-center">
                        <div className={`mx-auto flex items-center justify-center h-24 w-24 rounded-full mb-6 ${result.is_blocked ? 'bg-red-100 dark:bg-red-900' : 'bg-green-100 dark:bg-green-900'}`}>
                            {result.is_blocked ? (
                                <NoSymbolIcon className="h-16 w-16 text-red-600 dark:text-red-400" />
                            ) : (
                                <CheckCircleIcon className="h-16 w-16 text-green-600 dark:text-green-400" />
                            )}
                        </div>

                        <h1 className={`text-3xl font-black tracking-tight ${result.is_blocked ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'}`}>
                            {result.is_blocked ? 'Access revoked' : 'Exam submitted!'}
                        </h1>
                        <p className="mt-2 text-lg text-gray-500 dark:text-gray-400">
                            {result.is_blocked
                                ? 'Your access to this exam was revoked by an administrator.'
                                : 'Your answers have been recorded successfully.'}
                        </p>

                        {result.has_manual_grading && (
                            <div className="mb-8 bg-yellow-50 dark:bg-yellow-900/30 border-l-4 border-yellow-400 p-4 text-left">
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="ml-3">
                                        <p className="text-sm text-yellow-700 dark:text-yellow-200">
                                            <span className="font-bold">Attention:</span> Some questions are pending manual grading by your teacher. Your current score reflects only automatically graded questions.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
                            <div className="bg-gray-50 dark:bg-gray-700 overflow-hidden rounded-lg px-4 py-5 sm:p-6">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-300 truncate">Score</dt>
                                <dd className="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                                    {(Number(result.score) || 0).toFixed(2)} / {(Number(result.total_points) || 0).toFixed(2)}
                                    {result.pending_count > 0 && (
                                        <span className="block text-sm font-normal text-yellow-600 dark:text-yellow-400 mt-1">
                                            ({result.pending_count} questions pending grading)
                                        </span>
                                    )}
                                </dd>
                            </div>

                            <div className="bg-gray-50 dark:bg-gray-700 overflow-hidden rounded-lg px-4 py-5 sm:p-6">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-300 truncate">Correct answers</dt>
                                <dd className="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                                    {result.correct_answers} / {result.total_questions}
                                    {result.pending_count > 0 && (
                                        <span className="block text-sm font-normal text-yellow-600 dark:text-yellow-400 mt-1">
                                            (+{result.pending_count} pending review)
                                        </span>
                                    )}
                                </dd>
                            </div>

                            <div className="bg-gray-50 dark:bg-gray-700 overflow-hidden rounded-lg px-4 py-5 sm:p-6">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-300 truncate">Status</dt>
                                <dd className="mt-1 text-3xl font-semibold text-gray-900 dark:text-white capitalize">
                                    {result.status.replace('_', ' ')}
                                </dd>
                            </div>
                        </div>

                        <div className="mt-10">
                            <Link
                                to="/"
                                className="btn btn-primary inline-flex items-center"
                            >
                                <HomeIcon className="h-5 w-5 mr-2" />
                                Return to home
                            </Link>
                        </div>
                    </div>
                </motion.div>
            </div>
        </div>
    );
};

export default ResultPage;
