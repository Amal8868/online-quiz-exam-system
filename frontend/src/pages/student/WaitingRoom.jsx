import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { UserIcon } from '@heroicons/react/24/outline';
import { studentAPI } from '../../services/api';

const WaitingRoom = () => {
    const { quizId } = useParams();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [session, setSession] = useState(null);

    useEffect(() => {
        const sessionData = JSON.parse(localStorage.getItem('student_session'));
        if (!sessionData || String(sessionData.quiz.id) !== String(quizId)) { // Use strict equality
            navigate('/join');
            return;
        }
        setSession(sessionData);
        setLoading(false);

        const pollInterval = setInterval(async () => {
            try {
                const response = await studentAPI.getQuizStatus(quizId);
                const currentStatus = response.data.data.status;
                // setStatus(currentStatus); // status was unused

                if (currentStatus === 'started') {
                    clearInterval(pollInterval);

                    // Update session with official start time
                    const updatedSession = {
                        ...sessionData,
                        quiz: {
                            ...sessionData.quiz,
                            start_time: response.data.data.start_time,
                            server_time: response.data.data.server_time
                        }
                    };
                    localStorage.setItem('student_session', JSON.stringify(updatedSession));

                    navigate(`/exam/${sessionData.quiz.room_code || 'current'}`);
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, 3000);

        return () => clearInterval(pollInterval);
    }, [quizId, navigate]);

    if (loading) return null;

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
            <motion.div
                initial={{ opacity: 0, scale: 0.9 }}
                animate={{ opacity: 1, scale: 1 }}
                className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center"
            >
                <div className="mb-6">
                    <div className="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <UserIcon className="h-10 w-10 text-indigo-600 animate-pulse" />
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900 mb-2">Welcome, {session.student.name}!</h1>
                    <p className="text-gray-600">You have successfully joined</p>
                    <p className="text-lg font-semibold text-indigo-600">"{session.quiz.title}"</p>
                </div>

                <div className="space-y-6">
                    <div className="bg-indigo-50 border border-indigo-100 rounded-lg p-4">
                        <div className="flex items-center justify-center space-x-2 text-indigo-700 font-medium">
                            <span className="flex h-3 w-3 relative">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span>
                            </span>
                            <span>Waiting for teacher to start...</span>
                        </div>
                        <p className="text-xs text-indigo-600 mt-2">The exam will begin automatically. Please do not close this window.</p>
                    </div>

                    <div className="text-sm text-gray-500 italic">
                        "Your preparation today determines your result tomorrow."
                    </div>
                </div>
            </motion.div>
        </div>
    );
};

export default WaitingRoom;
