import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
    CheckCircleIcon,
    HomeIcon
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

    // const percentage = Math.round((result.score / result.total_questions) * 100);
    // Actually result.score is total points earned. We need total possible points.
    // Backend result table has score. Let's assume score is percentage or points.
    // Looking at StudentController: score is points_earned.
    // We need total points possible.
    // For now, let's just show the score.

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
            <div className="max-w-3xl mx-auto">
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden"
                >
                    <div className="px-4 py-5 sm:p-6 text-center">
                        <div className="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-green-100 dark:bg-green-900 mb-6">
                            <CheckCircleIcon className="h-16 w-16 text-green-600 dark:text-green-400" />
                        </div>

                        <h2 className="text-3xl font-extrabold text-gray-900 dark:text-white">Exam Submitted!</h2>
                        <p className="mt-2 text-lg text-gray-500 dark:text-gray-400">
                            Your answers have been recorded successfully.
                        </p>

                        <div className="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
                            <div className="bg-gray-50 dark:bg-gray-700 overflow-hidden rounded-lg px-4 py-5 sm:p-6">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-300 truncate">Score</dt>
                                <dd className="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                                    {result.score}
                                </dd>
                            </div>

                            <div className="bg-gray-50 dark:bg-gray-700 overflow-hidden rounded-lg px-4 py-5 sm:p-6">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-300 truncate">Correct Answers</dt>
                                <dd className="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                                    {result.correct_answers} / {result.total_questions}
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
                                Return to Home
                            </Link>
                        </div>
                    </div>
                </motion.div>
            </div>
        </div>
    );
};

export default ResultPage;
