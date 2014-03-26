<?php

namespace QXS\WorkerPool;

require_once(__DIR__.'/QXSWorkerPool.php');

/**
Example:
 */
class ClosureWorker implements Worker {
	protected $create=null;
	protected $run=null;
	protected $destroy=null;
	protected $storage=null;
	public function __construct(\Closure $run, \Closure $create=null, \Closure $destroy=null) {
		$this->storage=new \ArrayObject();
		if(is_null($create)) {
			$create=function($semaphore, $storage) { };
		}
		if(is_null($destroy)) {
			$destroy=function($semaphore, $storage) { };
		}
		$this->create=$create;
		$this->run=$run;
		$this->destroy=$destroy;
	}
	/**
	 * After the worker has been forked into another process
	 *
	 * @param \QXS\WorkerPool\Semaphore $semaphore the semaphore to run synchronized tasks
	 * @throws \Exception in case of a processing Error an Exception will be thrown
	 */
	public function onProcessCreate(Semaphore $semaphore) {
		$this->semaphore=$semaphore;
		$this->create->__invoke($this->semaphore, $this->storage);
	}
	/**
	 * Before the worker process is getting destroyed
	 *
	 * @throws \Exception in case of a processing Error an Exception will be thrown
	 */
	public function onProcessDestroy() {
		$this->destroy->__invoke($this->semaphore, $this->storage);
	}
	/**
	 * run the work
	 *
	 * @param Serializeable $input the data, that the worker should process
	 * @return Serializeable Returns the result
	 * @throws \Exception in case of a processing Error an Exception will be thrown
	 */
	public function run($input) {
		return $this->run->__invoke($input, $this->semaphore, $this->storage);
	}

}

