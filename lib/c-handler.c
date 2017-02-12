/*
 * Name:    NeoSentry NMS 
 * Author:  Dan Elliott
 * Description: This program will run scripts as root. 
 *  Hardcode the scripts and ensure they are read only.
 * 
 * Some Useful tutorials:
 *  https://www.infoq.com/articles/inotify-linux-file-system-event-monitoring
 *  http://www.enderunix.org/docs/eng/daemon.php
 *
 * This program will run scripts as root. Hardcode the scripts and ensure they are read only.
 *   
 * Set the owner of the executable to root and it will run as root.
 *  gcc -o program c-handler.c
 *  chown root program
 *  chmod u+s program   [or: chmod 4755 program]
 * 
 * To Limit access a little more to only owner and group do this:
 *  chown root:www-data handler
 *  chmod 4550 handler
 * 
 * COMMANDS
 *  ./handler ping.php
 *  ./handler traceroute.php [-d {ip}|all]
 *  
*/

#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <sys/types.h>
#include <unistd.h>

int main(int argc, char *argv[]) {
    /* Get the real and effective UID's for reporting */
    uid_t ruid = getuid ();    uid_t euid = geteuid ();
    int cmdrdy = 0;
    
    /* Ensure we have the right number of arguments */
    if (argc < 2) {
        printf("Usage: %s cmd [arguments]\n\nReal UID: %d Effective UID: %d\n", argv[0], ruid, euid);
        return 1;
    }
    
    /* Generate the command based on the argument*/
    if (strcmp(argv[1],"ping.php")==0) {
        cmdrdy=1;
    } else if (strcmp(argv[1],"traceroute.php")==0 && argc > 1) {
        cmdrdy=1;
    } else if (strcmp(argv[1],"snmpinfo.php")==0 && argc > 1) {
        cmdrdy=1;
    } else if (strcmp(argv[1],"cleanup.php")==0 && argc > 1) {
        cmdrdy=1;
    } else if (strcmp(argv[1],"sysconfig.php")==0 && argc > 1) {
        cmdrdy=1;
    }
    
    
    /* fork and execute */
    if (cmdrdy==1) {
        //++argv; /* remove the first argument */
        argv[0]="/usr/bin/php";
        
        int pid = fork();
        if(pid == 0){ /* the forked process will do this */
            printf("Child's PID is %d. Parent's PID is %d\n", getpid(), getppid());
            execv(argv[0],argv);
        } else { /* the main process will wait */
            wait(NULL);
            exit(0);
        }
        return 0;
    } else {
        /* no valid command was found */
        printf("No valid command was found for %s\n",argv[1]);
        return 1;
    }

}

int checkfromfile(char *arg, char *settingsFile) {
    /* Variables */
    /* char *scriptmapfile = "scripts/scriptmap.conf"; /* maps commands to script. root access only. */
    /* char *settingsfile = "settings.conf"; /* variable settings written by the front-end*/

    /* Read in the settings file to get the interpreter path and commands */
    FILE *fp;
    if (fp = fopen("../settings.conf","r")) {
        
    }
    fclose(fp);
    
}
