// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import {
  FArtstockInterface,
  FDepotInterface,
} from "../../../../../interface/FArticleInterface";
import {
  getListObjectSageMetadata,
  getSageMetadata,
} from "../../../../../functions/getMetadata";
import {
  FormInterface,
  TableLineItemInterface,
  TriggerFormContentChanged,
} from "../../../../../interface/InputInterface";
import { FormContentComponent } from "../../FormContentComponent";
import { AsPrincipalInput } from "../inputs/AsPrincipalInput";
import {
  createFormContent,
  handleFormIsValid,
} from "../../../../../functions/form";
import { FormInput } from "../../fields/FormInput";
import { numberValidator } from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

const fDepots: FDepotInterface[] = JSON.parse(
  $(`[data-${TOKEN}-fdepots]`).attr(`data-${TOKEN}-fdepots`) ?? "[]",
);

export const ArticleDepotTraitementComponent = React.forwardRef(
  (props, ref) => {
    const prefix = "fArtstocks";
    const [fArtstocks, setFArtstocks] = React.useState<FArtstockInterface[]>(
      getListObjectSageMetadata(prefix, articleMeta, "deNo"),
    );
    const getDefaultDeNo = (): string => {
      return (
        fArtstocks
          .find((x) => x.asPrincipal.toString() === "1")
          ?.deNo?.toString() ?? ""
      );
    };
    const [selectedDeNo, setDefaultDeNo] = React.useState<string>(getDefaultDeNo());
    const onAsPrincipalChanged: TriggerFormContentChanged = (
      name,
      newValue,
    ) => {
      setDefaultDeNo(newValue);
    };

    const getForm = (): FormInterface => {
      return {
        content: createFormContent({
          props: {
            container: true,
            spacing: 1,
            sx: { p: 1 },
          },
          children: [
            {
              props: {
                size: { xs: 12 },
              },
              table: {
                headers: [
                  "",
                  translations.words.intitule,
                  translations.words.main,
                  translations.words.asQteMini,
                  translations.words.asQteMaxi,
                ],
                removeItem: (fDepot: FDepotInterface) => {
                  setFArtstocks((v) => {
                    const r = v.filter(
                      (fArtstock) =>
                        fArtstock.deNo.toString() !== fDepot.deNo.toString(),
                    );
                    if (
                      r.length > 0 &&
                      !r.find((x) => x.asPrincipal.toString() === "1")
                    ) {
                      r[0].asPrincipal = 1;
                    }
                    return r;
                  });
                },
                add: {
                  table: {
                    headers: [translations.words.intitule],
                    addItem: (fDepot: FDepotInterface) => {
                      setFArtstocks((v) => {
                        let principal = 0;
                        if (!v.find((x) => x.asPrincipal.toString() === "1")) {
                          principal = 1;
                        }
                        return [
                          ...v,
                          {
                            deNo: fDepot.deNo,
                            asPrincipal: principal,
                            asQteMaxi: 0,
                            asQteMini: 0,
                          },
                        ];
                      });
                    },
                    search: (item: FDepotInterface, search: string) => {
                      return item.deIntitule
                        .toLowerCase()
                        .includes(search.toLowerCase());
                    },
                    items: fDepots
                      .filter(
                        (fDepot) =>
                          fArtstocks.find(
                            (fArtstock) =>
                              fArtstock.deNo.toString() ===
                              fDepot.deNo.toString(),
                          ) === undefined,
                      )
                      .map((fDepot): TableLineItemInterface => {
                        return {
                          item: fDepot,
                          lines: [
                            {
                              Dom: <span>{fDepot.deIntitule}</span>,
                            },
                          ],
                        };
                      }),
                  },
                },
                items: fArtstocks.map((fArtstock): TableLineItemInterface => {
                  const fDepot = fDepots.find(
                    (f) => f.deNo.toString() === fArtstock.deNo.toString(),
                  );
                  return {
                    item: fDepot,
                    lines: [
                      {
                        field: {
                          name: prefix + "[" + fDepot.deNo + "].deNo",
                          DomField: FormInput,
                          type: "hidden",
                          hideLabel: true,
                          initValues: {
                            value: fDepot.deNo,
                          },
                        },
                      },
                      {
                        Dom: <span>{fDepot.deIntitule}</span>,
                      },
                      {
                        Dom: (
                          <AsPrincipalInput
                            selectedDeNo={selectedDeNo}
                            deNo={fDepot.deNo}
                            onAsPrincipalChangedParent={onAsPrincipalChanged}
                            ref={React.createRef()}
                          />
                        ),
                      },
                      ...["asQteMini", "asQteMaxi"].map((f) => {
                        return {
                          field: {
                            name: prefix + "[" + fDepot.deNo + "]." + f,
                            DomField: FormInput,
                            type: "number",
                            hideLabel: true,
                            initValues: {
                              value: getSageMetadata(
                                prefix + "[" + fDepot.deNo + "]." + f,
                                articleMeta,
                                // @ts-ignore
                                fArtstock[f],
                              ),
                              validator: {
                                functionName: numberValidator,
                                params: {
                                  positive: true,
                                  canBeEmpty: true,
                                },
                              },
                            },
                          },
                        };
                      }),
                    ],
                  };
                }),
              },
            },
          ],
        }),
      };
    };

    const [form, setForm] = React.useState<FormInterface>(getForm());

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<boolean> {
        return await handleFormIsValid(form.content);
      },
    }));

    React.useEffect(() => {
      setForm(getForm());
    }, [fArtstocks, selectedDeNo]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      setDefaultDeNo(getDefaultDeNo());
    }, [fArtstocks]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <FormContentComponent content={form.content} transPrefix="fArticles" />
    );
  },
);
