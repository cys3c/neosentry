<?php

if (extension_loaded("pthreads")) {
	    echo "Using pthreads\n";
} else  echo "Using polyfill\n";

$pool = new Pool(4);

$pool->submit(new class extends Threaded Implements Collectable {
	public function run() {
		echo "Hello World\n";
		$this->garbage = true;
	}

	public function isGarbage() : bool { 
		return $this->garbage; 
	}

	private $garbage = false;
});

while ($pool->collect(function(Collectable $task){
	return $task->isGarbage();
})) continue;

$pool->shutdown();


/*

class workerThread extends Threaded {
public function __construct($i){
  $this->i=$i;
}

public function run(){
  echo "Running thread " . $this->i;
  sleep(rand(1,3));
  echo "Done with thread " . $this->i;
}
}

for($i=0;$i<50;$i++){
	$workers[$i]=new workerThread($i);
	$workers[$i]->start();
	echo " > Thread count is " . count(workerThread);
}
//*/
