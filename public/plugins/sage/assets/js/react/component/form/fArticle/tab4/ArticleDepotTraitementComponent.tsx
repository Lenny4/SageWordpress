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
  FormContentInterface,
  FormInterface,
  TableLineItemInterface,
} from "../../../../../interface/InputInterface";
import { FormInput } from "../../FormInput";
import { FormContentComponent } from "../../FormContentComponent";
import { FormCheckbox } from "../../FormCheckbox";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

const fDepots: FDepotInterface[] = JSON.parse(
  $("[data-sage-fdepots]").attr("data-sage-fdepots") ?? "[]",
);

export const ArticleDepotTraitementComponent = React.forwardRef(
  (props, ref) => {
    const prefix = "fArtstocks";
    const [fArtstocks, setFArtstocks] = React.useState<FArtstockInterface[]>(
      getListObjectSageMetadata(prefix, articleMeta, "deNo"),
    );

    const getForm = (): FormInterface => {
      const formContent: FormContentInterface[] = [
        {
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
                  "",
                  "",
                ],
                removeItem: (fDepot: FDepotInterface) => {
                  setFArtstocks((v) => {
                    return v.filter(
                      (fArtstock) =>
                        fArtstock.deNo.toString() !== fDepot.deNo.toString(),
                    );
                  });
                },
                add: {
                  table: {
                    headers: [translations.words.intitule],
                    addItem: (fDepot: FDepotInterface) => {
                      setFArtstocks((v) => {
                        return [
                          ...v,
                          {
                            deNo: fDepot.deNo,
                            asPrincipal: 0,
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
                        field: {
                          name: prefix + "[" + fDepot.deNo + "].asPrincipal",
                          hideLabel: true,
                          DomField: FormCheckbox,
                          initValues: {
                            value:
                              getSageMetadata(
                                prefix + "[" + fDepot.deNo + "].asPrincipal",
                                articleMeta,
                              ) ?? "",
                          },
                        },
                      },
                      ...["asQteMini", "asQteMaxi"].map((f) => {
                        return {
                          field: {
                            name: prefix + "[" + fDepot.deNo + "]." + f,
                            DomField: FormInput,
                            type: "number",
                            hideLabel: true,
                            initValues: {
                              value:
                                getSageMetadata(
                                  prefix + "[" + fDepot.deNo + "]." + f,
                                  articleMeta,
                                ) ?? "",
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
        },
      ];
      return {
        content: formContent,
      };
    };

    const [form, setForm] = React.useState<FormInterface>(getForm());

    useImperativeHandle(ref, () => ({
      getForm() {
        return form;
      },
    }));

    React.useEffect(() => {
      setForm(getForm());
    }, [fArtstocks]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <FormContentComponent content={form.content} transPrefix="fArticles" />
    );
  },
);
