declare function once(
  id: string,
  selector: NodeList | Array<Element> | Element | string,
  context: Document | Element
): Element[];

once.remove = (
  id: string,
  selector: NodeList | Array<Element> | Element | string,
  context: Document | Element
): Element[] => {};
once.filter = (
  id: string,
  selector: NodeList | Array<Element> | Element | string,
  context: Document | Element
): Element[] => {};
once.find = (id: string, context: Document | Element): Element[] => {};
