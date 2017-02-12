/*
    daemonize.c
    This example daemonizes a process, writes a few log messages,
    sleeps 20 seconds and terminates afterwards.
    
    http://www.enderunix.org/docs/eng/daemon.php

    To compile:	cc -o cdaemon c-daemonize.c
    To run:		./cdaemon
    To test daemon:	ps -ef|grep cdaemon (or ps -aux on BSD systems)
    To test log:	grep cdaemon /var/log/syslog
    To test signal:	kill -HUP `cat /tmp/exampled.lock`
    To terminate:	kill `cat /tmp/exampled.lock`
 */

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <signal.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <syslog.h>

static void skeleton_daemon()
{
    pid_t pid;
    if(getppid()==1) return; /* already a daemon */

    /* Fork off the parent process */
    pid = fork();

    /* An error occurred */
    if (pid < 0)
        exit(EXIT_FAILURE);

    /* Success: Let the parent terminate */
    if (pid > 0)
        exit(EXIT_SUCCESS);

    /* On success: The child process becomes session leader */
    if (setsid() < 0)
        exit(EXIT_FAILURE);

    /* Catch, ignore and handle signals */
    //TODO: Implement a working signal handler */
    signal(SIGCHLD,SIG_IGN); /* ignore child */
    signal(SIGTSTP,SIG_IGN); /* ignore tty signals */
    signal(SIGTTOU,SIG_IGN);
    signal(SIGTTIN,SIG_IGN);
    signal(SIGHUP,signal_handler); /* catch hangup signal */
    signal(SIGTERM,signal_handler); /* catch kill signal */

    /* Fork off for the second time*/
    pid = fork();

    /* An error occurred */
    if (pid < 0)
        exit(EXIT_FAILURE);

    /* Success: Let the parent terminate */
    if (pid > 0)
        exit(EXIT_SUCCESS);

    /* Set new file permissions */
    umask(0);

    /* Change the working directory to the root directory */
    /* or another appropriated directory */
    chdir("/");

    /* Close all open file descriptors */
    int x;
    for (x = sysconf(_SC_OPEN_MAX); x>0; x--)
    {
        close (x);
    }

    /* Open the log file */
    openlog ("firstdaemon", LOG_PID, LOG_DAEMON);
}

void signal_handler(sig)
int sig;
{
    switch(sig) {
    case SIGHUP:
        syslog (LOG_NOTICE,"hangup signal caught.");
        break;
    case SIGTERM:
        syslog (LOG_NOTICE,"terminate signal caught.");
        exit(0);
        break;
    }
}

int main()
{
    skeleton_daemon();

    while (1)
    {
        //TODO: Insert daemon code here.
        syslog (LOG_NOTICE, "First daemon started.");
        sleep (20);
        break;
    }

    syslog (LOG_NOTICE, "First daemon terminated.");
    closelog();

    return EXIT_SUCCESS;
}
